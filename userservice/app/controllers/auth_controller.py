from app.models.user_model import User, TokenBlocklist
from app import db, bcrypt
from flask_jwt_extended import create_access_token, get_jwt

class AuthController:
    @staticmethod
    def register(name, email, password, role='user'):
        # Cek apakah email sudah ada
        if User.query.filter_by(email=email).first():
            return {
                "status": "gagal",
                "message": "Email sudah terdaftar",
                "data": None
            }, 400
        
        hashed_password = bcrypt.generate_password_hash(password).decode('utf-8')
        new_user = User(name=name, email=email, password=hashed_password, role=role)
        
        db.session.add(new_user)
        db.session.commit()

        # Kirim event ke RabbitMQ dalam format yang dimengerti Laravel
        from app.rabbitmq import send_to_queue
        laravel_job = {
            "uuid": str(os.urandom(16).hex()),
            "displayName": "App\\Jobs\\HandleUserRegistered",
            "job": "Illuminate\\Queue\\CallQueuedHandler@call",
            "maxTries": None,
            "maxExceptions": None,
            "fail_on_timeout": False,
            "backoff": None,
            "timeout": None,
            "data": {
                "commandName": "App\\Jobs\\HandleUserRegistered",
                "command": f'O:31:"App\\Jobs\\HandleUserRegistered":1:{{s:4:"data";a:2:{{s:5:"event";s:15:"user.registered";s:4:"data";a:4:{{s:2:"id";i:{new_user.id};s:4:"name";s:{len(new_user.name)}:"{new_user.name}";s:5:"email";s:{len(new_user.email)}:"{new_user.email}";s:4:"role";s:{len(new_user.role)}:"{new_user.role}";}}}}}}'
            }
        }
        # Karena serialization PHP manual sangat rumit, kita kirim RAW JSON saja 
        # dan nanti kita beri tahu Laravel untuk membacanya sebagai plain message.
        
        user_event = {
            "event": "user.registered",
            "data": {
                "id": new_user.id,
                "name": new_user.name,
                "email": new_user.email,
                "role": new_user.role
            }
        }
        send_to_queue('user_events', user_event)

        return {
            "status": "berhasil",
            "message": "User berhasil didaftarkan",
            "data": new_user.to_dict()
        }, 201

    @staticmethod
    def login(email, password):
        user = User.query.filter_by(email=email).first()
        
        if user and bcrypt.check_password_hash(user.password, password):
            access_token = create_access_token(
                identity=str(user.id), 
                additional_claims={"role": user.role}
            )
            return {
                "status": "berhasil",
                "message": "Login berhasil",
                "data": {
                    "access_token": access_token,
                    "user": user.to_dict()
                }
            }, 200
        
        return {
            "status": "gagal",
            "message": "Email atau password salah",
            "data": None
        }, 401
    
    @staticmethod
    def refresh_token(id, jti):
        blocked_token = TokenBlocklist(jti=jti)
        db.session.add(blocked_token)
        
        user = User.query.filter_by(id=id).first()
        if not user:
            db.session.commit() 
            return {
                "status": "gagal",
                "message": "User tidak ditemukan",
                "data": None
            }, 404
            
        access_token = create_access_token(
            identity=str(user.id), 
            additional_claims={"role": user.role}
        )
        
        db.session.commit()
        
        return {
            "status": "berhasil",
            "message": "Token berhasil diperbarui, token lama telah dicabut",
            "data": {
                "access_token": access_token,
                "user": user.to_dict()
            }
        }, 200

    @staticmethod
    def logout(jti):
        blocked_token = TokenBlocklist(jti=jti)
        db.session.add(blocked_token)
        db.session.commit()
        
        return {
            "status": "berhasil",
            "message": "Logout berhasil, token telah dicabut",
            "data": None
        }, 200
