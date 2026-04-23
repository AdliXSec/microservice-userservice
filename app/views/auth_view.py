from flask import Blueprint, request, jsonify
from app.controllers.auth_controller import AuthController
from flask_jwt_extended import jwt_required, get_jwt

auth_bp = Blueprint('auth_bp', __name__)

@auth_bp.route('/register', methods=['POST'])
def register_user():
    data = request.get_json()
    if not data or 'name' not in data or 'email' not in data or 'password' not in data:
        return jsonify({"error": "Missing name, email, or password"}), 400
    
    role = data.get('role', 'user')
    response, status_code = AuthController.register(data['name'], data['email'], data['password'], role)
    return jsonify(response), status_code

@auth_bp.route('/login', methods=['POST'])
def login_user():
    data = request.get_json()
    if not data or 'email' not in data or 'password' not in data:
        return jsonify({"error": "Missing email or password"}), 400
    
    response, status_code = AuthController.login(data['email'], data['password'])
    return jsonify(response), status_code

@auth_bp.route('/refresh', methods=['GET'])
@jwt_required()
def refresh_token():
    user_id = get_jwt().get("sub")
    response, status_code = AuthController.refresh_token(user_id)
    return jsonify(response), status_code