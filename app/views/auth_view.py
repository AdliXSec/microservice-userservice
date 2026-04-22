from flask import Blueprint, request, jsonify
from app.controllers.auth_controller import AuthController

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
