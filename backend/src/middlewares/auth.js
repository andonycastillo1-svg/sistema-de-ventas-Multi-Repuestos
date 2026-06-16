'use strict';

const jwt = require('jsonwebtoken');

function getBearerToken(authorizationHeader) {
  if (!authorizationHeader) {
    return null;
  }

  const parts = authorizationHeader.split(' ');
  if (parts.length !== 2 || parts[0] !== 'Bearer') {
    return null;
  }

  return parts[1];
}

function requireAuth(req, res, next) {
  const token = getBearerToken(req.headers.authorization);

  if (!token) {
    return res.status(401).json({ message: 'Token de autenticación requerido.' });
  }

  try {
    const payload = jwt.verify(token, process.env.JWT_SECRET);

    req.user = {
      id: payload.sub,
      usuario: payload.usuario,
      nombre: payload.nombre,
      rol: payload.rol
    };

    return next();
  } catch (error) {
    return res.status(401).json({ message: 'Token inválido o expirado.' });
  }
}

module.exports = {
  requireAuth
};
