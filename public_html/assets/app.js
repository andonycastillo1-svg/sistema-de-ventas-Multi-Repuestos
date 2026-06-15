'use strict';

(function () {
  var token = window.localStorage.getItem('token');
  var cart = [];

  function byId(id) {
    return document.getElementById(id);
  }

  function showMessage(type, text) {
    var message = byId('message');
    if (!message) {
      window.alert(text);
      return;
    }

    message.className = 'alert mt-3 alert-' + type;
    message.textContent = text;
    message.classList.remove('d-none');
  }

  function setBusy(button, busy, busyText) {
    if (!button) {
      return;
    }

    if (busy) {
      button.dataset.originalText = button.textContent;
      button.textContent = busyText;
      button.disabled = true;
      return;
    }

    button.textContent = button.dataset.originalText || button.textContent;
    button.disabled = false;
  }

  function setLoggedIn(isLoggedIn) {
    byId('loginCard').classList.toggle('d-none', isLoggedIn);
    byId('posCard').classList.toggle('d-none', !isLoggedIn);
    byId('logoutBtn').classList.toggle('d-none', !isLoggedIn);
  }

  function renderCart() {
    var cartBody = byId('cartBody');
    cartBody.innerHTML = '';

    cart.forEach(function (item, index) {
      var row = document.createElement('tr');
      row.innerHTML = '<td>' + item.productoId + '</td>' +
        '<td class="text-end">' + item.cantidad + '</td>' +
        '<td class="text-end"><button class="btn btn-sm btn-outline-danger" data-index="' + index + '">Quitar</button></td>';
      cartBody.appendChild(row);
    });
  }

  function parseResponse(response) {
    return response.text().then(function (text) {
      var body = {};

      if (text) {
        try {
          body = JSON.parse(text);
        } catch (error) {
          if (text.toLowerCase().indexOf('file not found') !== -1) {
            throw new Error('Archivo PHP no encontrado en el hosting. Verifica que exista la ruta solicitada y sube public_html/api/login.php si estás intentando ingresar. Respuesta: ' + text.slice(0, 180));
          }

          throw new Error('La API no respondió JSON válido. Respuesta: ' + text.slice(0, 180));
        }
      }

      if (!response.ok) {
        if (body.code === 'PASSWORD_MISMATCH') {
          throw new Error('La contraseña no coincide con la guardada. Restablécela con public_html/tools/reset_password.php. (PASSWORD_MISMATCH)');
        }

        if (body.code === 'USER_NOT_FOUND') {
          throw new Error('No existe ese usuario o correo en la base configurada. Crea el usuario o revisa la base de datos. (USER_NOT_FOUND)');
        }

        if (body.code === 'USER_INACTIVE') {
          throw new Error('El usuario existe, pero está inactivo. Cambia estado a ACTIVO o usa reset_password.php. (USER_INACTIVE)');
        }

        var suffix = body.code ? ' (' + body.code + ')' : '';
        throw new Error((body.message || 'Error HTTP ' + response.status) + suffix);
      }

      return body;
    });
  }

  function apiFetch(url, options) {
    options = options || {};
    options.headers = options.headers || {};
    options.headers['Content-Type'] = 'application/json';
    options.headers.Accept = 'application/json';

    if (token) {
      options.headers.Authorization = 'Bearer ' + token;
    }

    return fetch(url, options).then(parseResponse).catch(function (error) {
      if (error && error.message) {
        throw error;
      }
      throw new Error('No se pudo conectar con la API. Revisa la ruta, HTTPS y permisos del hosting.');
    });
  }

  function initLogin() {
    byId('loginForm').addEventListener('submit', function (event) {
      event.preventDefault();

      var submitButton = byId('loginSubmitBtn');
      setBusy(submitButton, true, 'Verificando...');
      showMessage('info', 'Validando usuario y contraseña...');

      apiFetch('api/login.php', {
        method: 'POST',
        body: JSON.stringify({
          usuario: byId('loginUsuario').value,
          password: byId('loginPassword').value
        })
      }).then(function (body) {
        if (!body.token) {
          throw new Error('La API respondió sin token. Revisa api/login.php.');
        }

        token = body.token;
        window.localStorage.setItem('token', token);
        setLoggedIn(true);
        showMessage('success', 'Sesión iniciada correctamente.');
      }).catch(function (error) {
        console.error(error);
        showMessage('danger', error.message || 'No se pudo iniciar sesión.');
      }).finally(function () {
        setBusy(submitButton, false);
      });
    });
  }

  function initCart() {
    byId('addItemForm').addEventListener('submit', function (event) {
      event.preventDefault();

      cart.push({
        productoId: Number(byId('productoId').value),
        cantidad: Number(byId('cantidad').value)
      });

      renderCart();
      event.target.reset();
      byId('cantidad').value = 1;
    });

    byId('cartBody').addEventListener('click', function (event) {
      if (event.target.dataset.index) {
        cart.splice(Number(event.target.dataset.index), 1);
        renderCart();
      }
    });

    byId('clearCartBtn').addEventListener('click', function () {
      cart = [];
      renderCart();
    });

    byId('emitSaleBtn').addEventListener('click', function () {
      var submitButton = byId('emitSaleBtn');
      setBusy(submitButton, true, 'Emitiendo...');
      showMessage('info', 'Procesando venta...');

      apiFetch('api/venta.php', {
        method: 'POST',
        body: JSON.stringify({ items: cart, metodoPago: 'EFECTIVO' })
      }).then(function (body) {
        cart = [];
        renderCart();
        showMessage('success', 'Venta emitida: ' + body.folio + ' Total: ' + body.total);
      }).catch(function (error) {
        console.error(error);
        showMessage('danger', error.message || 'No se pudo emitir la venta.');
      }).finally(function () {
        setBusy(submitButton, false);
      });
    });
  }

  function initHealthCheck() {
    var healthBtn = byId('healthCheckBtn');
    if (!healthBtn) {
      return;
    }

    healthBtn.addEventListener('click', function () {
      setBusy(healthBtn, true, 'Probando...');
      showMessage('info', 'Probando conexión con api/health.php...');

      apiFetch('api/health.php').then(function (body) {
        var missing = [];
        var files = body.files || {};

        Object.keys(files).forEach(function (name) {
          if (files[name] !== 'ok') {
            missing.push(name);
          }
        });

        if (missing.length > 0) {
          showMessage('warning', 'MySQL conecta, pero faltan archivos en el hosting: ' + missing.join(', '));
          return;
        }

        showMessage('success', (body.message || 'API conectada.') + ' Archivos requeridos OK.');
      }).catch(function (error) {
        console.error(error);
        showMessage('danger', error.message || 'No se pudo probar la API.');
      }).finally(function () {
        setBusy(healthBtn, false);
      });
    });
  }

  function init() {
    window.addEventListener('error', function (event) {
      showMessage('danger', 'Error de JavaScript: ' + event.message);
    });

    window.addEventListener('unhandledrejection', function (event) {
      var reason = event.reason || {};
      showMessage('danger', 'Error inesperado: ' + (reason.message || reason));
    });

    initLogin();
    initCart();
    initHealthCheck();

    byId('logoutBtn').addEventListener('click', function () {
      token = null;
      window.localStorage.removeItem('token');
      setLoggedIn(false);
      showMessage('info', 'Sesión cerrada.');
    });

    setLoggedIn(Boolean(token));
    renderCart();
    showMessage('secondary', 'Aplicación cargada. Si no puedes entrar, pulsa "Probar conexión".');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
}());
