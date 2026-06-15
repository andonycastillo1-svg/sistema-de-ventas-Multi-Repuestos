'use strict';

var token = window.localStorage.getItem('token');
var cart = [];

var loginCard = document.getElementById('loginCard');
var posCard = document.getElementById('posCard');
var message = document.getElementById('message');
var logoutBtn = document.getElementById('logoutBtn');
var cartBody = document.getElementById('cartBody');

function showMessage(type, text) {
  message.className = 'alert mt-3 alert-' + type;
  message.textContent = text;
  message.classList.remove('d-none');
}

function setLoggedIn(isLoggedIn) {
  loginCard.classList.toggle('d-none', isLoggedIn);
  posCard.classList.toggle('d-none', !isLoggedIn);
  logoutBtn.classList.toggle('d-none', !isLoggedIn);
}

function renderCart() {
  cartBody.innerHTML = '';

  cart.forEach(function (item, index) {
    var row = document.createElement('tr');
    row.innerHTML = '<td>' + item.productoId + '</td>' +
      '<td class="text-end">' + item.cantidad + '</td>' +
      '<td class="text-end"><button class="btn btn-sm btn-outline-danger" data-index="' + index + '">Quitar</button></td>';
    cartBody.appendChild(row);
  });
}

function apiFetch(url, options) {
  options = options || {};
  options.headers = options.headers || {};
  options.headers['Content-Type'] = 'application/json';

  if (token) {
    options.headers.Authorization = 'Bearer ' + token;
  }

  return fetch(url, options).then(function (response) {
    return response.json().then(function (body) {
      if (!response.ok) {
        throw new Error(body.message || 'Error de servidor');
      }
      return body;
    });
  });
}

document.getElementById('loginForm').addEventListener('submit', function (event) {
  event.preventDefault();

  apiFetch('api/login.php', {
    method: 'POST',
    body: JSON.stringify({
      usuario: document.getElementById('loginUsuario').value,
      password: document.getElementById('loginPassword').value
    })
  }).then(function (body) {
    token = body.token;
    window.localStorage.setItem('token', token);
    setLoggedIn(true);
    showMessage('success', 'Sesión iniciada.');
  }).catch(function (error) {
    showMessage('danger', error.message);
  });
});

document.getElementById('addItemForm').addEventListener('submit', function (event) {
  event.preventDefault();

  cart.push({
    productoId: Number(document.getElementById('productoId').value),
    cantidad: Number(document.getElementById('cantidad').value)
  });

  renderCart();
  event.target.reset();
  document.getElementById('cantidad').value = 1;
});

cartBody.addEventListener('click', function (event) {
  if (event.target.dataset.index) {
    cart.splice(Number(event.target.dataset.index), 1);
    renderCart();
  }
});

document.getElementById('clearCartBtn').addEventListener('click', function () {
  cart = [];
  renderCart();
});

document.getElementById('emitSaleBtn').addEventListener('click', function () {
  apiFetch('api/venta.php', {
    method: 'POST',
    body: JSON.stringify({ items: cart, metodoPago: 'EFECTIVO' })
  }).then(function (body) {
    cart = [];
    renderCart();
    showMessage('success', 'Venta emitida: ' + body.folio + ' Total: ' + body.total);
  }).catch(function (error) {
    showMessage('danger', error.message);
  });
});

logoutBtn.addEventListener('click', function () {
  token = null;
  window.localStorage.removeItem('token');
  setLoggedIn(false);
});

setLoggedIn(Boolean(token));
renderCart();
