import os
from datetime import timedelta
from flask import Flask
from flask_sqlalchemy import SQLAlchemy
from flask_bcrypt import Bcrypt
from flask_jwt_extended import JWTManager
from dotenv import load_dotenv

# Muat variabel lingkungan dari file .env
load_dotenv()

db = SQLAlchemy()
bcrypt = Bcrypt()
jwt = JWTManager()

def create_app():
    app = Flask(__name__)

    # Konfigurasi dari Environment Variables (.env)
    app.config['SQLALCHEMY_DATABASE_URI'] = os.getenv('DATABASE_URL', 'mysql+pymysql://root:@localhost/flask_db')
    app.config['SQLALCHEMY_TRACK_MODIFICATIONS'] = False
    
    # Konfigurasi JWT
    app.config['JWT_SECRET_KEY'] = os.getenv('JWT_SECRET_KEY', 'super-secret-key-12345')
    
    # Atur durasi token (12 Jam sesuai permintaan)
    expires_hours = int(os.getenv('JWT_ACCESS_TOKEN_EXPIRES_HOURS', 12))
    app.config['JWT_ACCESS_TOKEN_EXPIRES'] = timedelta(hours=expires_hours)

    db.init_app(app)
    bcrypt.init_app(app)
    jwt.init_app(app)

    # Daftarkan Blueprint
    from app.routes.user_view import user_bp
    from app.routes.auth_view import auth_bp
    app.register_blueprint(user_bp)
    app.register_blueprint(auth_bp)

    return app
