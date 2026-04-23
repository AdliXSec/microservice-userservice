from app.models.user_model import User
from app import db, bcrypt
from flask_jwt_extended import create_access_token

class AuthController:
    @staticmethod
    def register(name, email, password, role='user'):
        # Cek apakah email sudah ada
        if User.query.filter_by(email=email).first():
            return {"error": "Email already exists"}, 400
        
        hashed_password = bcrypt.generate_password_hash(password).decode('utf-8')
        new_user = User(name=name, email=email, password=hashed_password, role=role)
        
        db.session.add(new_user)
        db.session.commit()
        return {"message": "User registered successfully", "user": new_user.to_dict()}, 201

    @staticmethod
    def login(email, password):
        user = User.query.filter_by(email=email).first()
        
        # Verifikasi password yang di-hash
        if user and bcrypt.check_password_hash(user.password, password):
            # Membuat token JWT (berlaku standar 1 jam)
            # Kita bisa menyimpan informasi user (seperti ID atau role) di dalam token
            access_token = create_access_token(identity=str(user.id), additional_claims={"role": user.role})
            return {
                "access_token": access_token,
                "user": user.to_dict()
            }, 200
        
        return {"error": "Invalid email or password"}, 401
    
    def refresh_token(id):
        user = User.query.get(id)
        access_token = create_access_token(identity=str(user.id), additional_claims={"role": user.role})
        return {
            "access_token": access_token,
            "user": user.to_dict()
        }, 200
        
