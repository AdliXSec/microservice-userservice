from flask import Flask
from flask_sqlalchemy import SQLAlchemy
from flask_bcrypt import Bcrypt
from flask_jwt_extended import JWTManager

db = SQLAlchemy()
bcrypt = Bcrypt()
jwt = JWTManager()

def create_app():
    app = Flask(__name__)

    # Konfigurasi MySQL
    app.config['SQLALCHEMY_DATABASE_URI'] = 'mysql+pymysql://root:@localhost/flask_db'
    app.config['SQLALCHEMY_TRACK_MODIFICATIONS'] = False
    
    # Konfigurasi JWT (Ganti secret key ini dengan yang lebih aman nanti)
    app.config['JWT_SECRET_KEY'] = 'super-secret-key-12345'

    db.init_app(app)
    bcrypt.init_app(app)
    jwt.init_app(app)

    # Daftarkan Blueprint
    from app.routes.user_view import user_bp
    from app.routes.auth_view import auth_bp
    app.register_blueprint(user_bp)
    app.register_blueprint(auth_bp)

    return app
