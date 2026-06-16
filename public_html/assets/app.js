'use strict';

(function () {
  var token = window.localStorage.getItem('token');
  var cart = [];
  var productsCache = [];
  var purchaseItems = [];
  var currentUser = JSON.parse(window.localStorage.getItem('currentUser') || 'null');

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

  function showModule(moduleName) {
    Array.prototype.forEach.call(document.querySelectorAll('.module-view'), function (section) {
      section.classList.add('d-none');
    });

    byId('mainMenu').classList.toggle('d-none', moduleName !== 'menu');

    if (moduleName !== 'menu') {
      var target = byId('module-' + moduleName);
      if (target) {
        target.classList.remove('d-none');
      }
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function setLoggedIn(isLoggedIn) {
    byId('loginCard').classList.toggle('d-none', isLoggedIn);
    byId('mainMenu').classList.toggle('d-none', !isLoggedIn);
    byId('logoutBtn').classList.toggle('d-none', !isLoggedIn);
    byId('homeBtn').classList.toggle('d-none', !isLoggedIn);

    if (!isLoggedIn) {
      Array.prototype.forEach.call(document.querySelectorAll('.module-view'), function (section) {
        section.classList.add('d-none');
      });
    }
  }


  function currentRole() {
    var role = currentUser && currentUser.rol ? String(currentUser.rol).toUpperCase() : '';
    return role === 'ADMIN' ? 'ADMINISTRADOR' : role;
  }

  function applyRolePermissions() {
    var role = currentRole();
    var adminOnly = ['productos', 'compras', 'usuarios', 'bancos'];

    adminOnly.forEach(function (moduleName) {
      Array.prototype.forEach.call(document.querySelectorAll('[data-module="' + moduleName + '"]'), function (element) {
        element.classList.toggle('d-none', role !== 'ADMINISTRADOR');
      });
    });
  }

  function renderCart() {
    var cartBody = byId('cartBody');
    cartBody.innerHTML = '';

    cart.forEach(function (item, index) {
      var row = document.createElement('tr');
      row.innerHTML = '<td>' + (item.nombre || item.productoId) + '</td>' +
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
          throw new Error('La contraseña no coincide con la guardada. Abre tools/reset_password.php?key=123321, restablece la contraseña de este usuario y vuelve a intentar. (PASSWORD_MISMATCH)');
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

  function appendTokenToUrl(url) {
    if (!token) {
      return url;
    }

    var separator = url.indexOf('?') === -1 ? '?' : '&';
    return url + separator + 'token=' + encodeURIComponent(token);
  }

  function apiFetch(url, options) {
    options = options || {};
    options.headers = options.headers || {};
    options.headers['Content-Type'] = 'application/json';
    options.headers.Accept = 'application/json';

    if (token) {
      options.headers.Authorization = 'Bearer ' + token;
      url = appendTokenToUrl(url);
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
        currentUser = body.usuario || null;
        window.localStorage.setItem('token', token);
        window.localStorage.setItem('currentUser', JSON.stringify(currentUser));
        setLoggedIn(true);
        applyRolePermissions();
        showModule('menu');
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

      var selected = byId('productoBusqueda').value;
      var match = selected.match(/^#(\d+)/);
      if (!match) {
        showMessage('danger', 'Selecciona un producto válido de la lista por nombre/SKU.');
        return;
      }

      cart.push({
        productoId: Number(match[1]),
        nombre: selected,
        cantidad: Number(byId('cantidad').value)
      });

      renderCart();
      event.target.reset();
      byId('cantidad').value = 1;
      byId('productoBusqueda').value = '';
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
        body: JSON.stringify({ items: cart, metodoPago: byId('metodoPago').value, descuento: Number(byId('ventaDescuento').value || 0) })
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


  function formToObject(form) {
    var data = {};
    Array.prototype.forEach.call(form.elements, function (element) {
      if (!element.name) {
        return;
      }
      data[element.name] = element.type === 'number' ? Number(element.value || 0) : element.value;
    });
    return data;
  }

  function productLabel(product) {
    var vehicle = [product.marca_vehiculo, product.modelo_vehiculo, product.anio_inicio && product.anio_fin ? product.anio_inicio + '-' + product.anio_fin : '', product.motor].filter(Boolean).join(' ');
    return '#' + product.id + ' | ' + product.sku + ' | ' + product.nombre + (vehicle ? ' | ' + vehicle : '');
  }

  function renderProducts(productos) {
    var target = byId('productsList');
    var datalist = byId('productosDatalist');
    productsCache = productos || [];

    if (datalist) {
      datalist.innerHTML = productsCache.map(function (p) {
        return '<option value="' + productLabel(p).replace(/"/g, '&quot;') + '">';
      }).join('');
    }

    if (!target) {
      return;
    }

    target.innerHTML = '<strong>Últimos repuestos:</strong><br>' + productsCache.slice(0, 8).map(function (p) {
      return productLabel(p) + ' | Stock: ' + p.stock_actual + ' | Costo: ' + p.costo_compra + ' | Precio final: ' + p.precio_final;
    }).join('<br>');
  }

  function loadProducts() {
    return apiFetch('api/productos.php').then(function (body) {
      renderProducts(body.productos || []);
    });
  }

  function loadPurchases() {
    return apiFetch('api/compras.php').then(function (body) {
      var target = byId('purchasesList');
      if (!target) {
        return;
      }
      target.innerHTML = '<strong>Últimas compras:</strong><br>' + (body.compras || []).slice(0, 5).map(function (c) {
        return c.folio + (c.factura_numero ? ' | Factura: ' + c.factura_numero : '') + ' | ' + c.proveedor + ' | Fecha: ' + c.fecha + ' | Total: ' + c.total;
      }).join('<br>');
    });
  }

  function loadBanks() {
    return apiFetch('api/bancos.php').then(function (body) {
      var target = byId('banksList');
      if (!target) {
        return;
      }
      target.innerHTML = '<strong>Bancos:</strong><br>' + (body.bancos || []).map(function (b) {
        return '#' + b.id + ' ' + b.nombre + ' ' + (b.numero_cuenta || '');
      }).join('<br>');
    });
  }


  function renderPurchaseItems() {
    var body = byId('purchaseItemsBody');
    if (!body) {
      return;
    }

    body.innerHTML = purchaseItems.map(function (item, index) {
      return '<tr><td>' + item.nombre + '</td><td class="text-end">' + item.cantidad + '</td><td class="text-end">' + item.costoUnitario + '</td><td class="text-end"><button class="btn btn-sm btn-outline-danger" data-purchase-index="' + index + '" type="button">Quitar</button></td></tr>';
    }).join('');
  }

  function selectedProductIdFromInput(inputId) {
    var selected = byId(inputId).value;
    var match = selected.match(/^#(\d+)/);
    if (!match) {
      return null;
    }
    return { id: Number(match[1]), label: selected };
  }


  function loadInventory(filter) {
    filter = filter || 'todos';
    return apiFetch('api/inventario.php?filtro=' + encodeURIComponent(filter)).then(function (body) {
      var summary = body.resumen || {};
      var summaryTarget = byId('inventorySummary');
      var tbody = byId('inventoryBody');

      if (summaryTarget) {
        summaryTarget.textContent = 'Total productos: ' + (summary.total_productos || 0) + ' | Bajo mínimo: ' + (summary.bajo_minimo || 0) + ' | Sin stock: ' + (summary.sin_stock || 0);
      }

      if (tbody) {
        tbody.innerHTML = (body.productos || []).map(function (p) {
          var badge = p.alerta === 'SIN_STOCK' ? 'danger' : (p.alerta === 'BAJO_MINIMO' ? 'warning' : 'success');
          var vehicle = [p.marca_vehiculo, p.modelo_vehiculo, p.anio_inicio && p.anio_fin ? p.anio_inicio + '-' + p.anio_fin : '', p.motor].filter(Boolean).join(' ');
          return '<tr><td>#' + p.id + ' ' + p.sku + ' - ' + p.nombre + '</td><td>' + vehicle + '</td><td>' + (p.ubicacion || '') + '</td><td class="text-end">' + p.stock_actual + '</td><td class="text-end">' + p.stock_minimo + '</td><td><span class="badge text-bg-' + badge + '">' + p.alerta + '</span></td></tr>';
        }).join('');
      }
    });
  }

  function initBusinessModules() {
    byId('productForm').addEventListener('submit', function (event) {
      event.preventDefault();
      apiFetch('api/productos.php', { method: 'POST', body: JSON.stringify(formToObject(event.target)) }).then(function (body) {
        showMessage('success', body.message || 'Producto creado.');
        event.target.reset();
        return loadProducts();
      }).catch(function (error) {
        showMessage('danger', error.message);
      });
    });

    byId('addPurchaseItemBtn').addEventListener('click', function () {
      var product = selectedProductIdFromInput('purchaseProductoBusqueda');
      var cantidad = Number(byId('purchaseCantidad').value || 0);
      var costoUnitario = Number(byId('purchaseCosto').value || 0);

      if (!product || cantidad <= 0 || costoUnitario < 0) {
        showMessage('danger', 'Selecciona un repuesto válido, cantidad y costo para agregarlo a la factura.');
        return;
      }

      purchaseItems.push({ productoId: product.id, nombre: product.label, cantidad: cantidad, costoUnitario: costoUnitario });
      byId('purchaseProductoBusqueda').value = '';
      byId('purchaseCantidad').value = '';
      byId('purchaseCosto').value = '';
      renderPurchaseItems();
    });

    byId('purchaseItemsBody').addEventListener('click', function (event) {
      if (event.target.dataset.purchaseIndex) {
        purchaseItems.splice(Number(event.target.dataset.purchaseIndex), 1);
        renderPurchaseItems();
      }
    });

    byId('purchaseForm').addEventListener('submit', function (event) {
      event.preventDefault();
      var data = formToObject(event.target);

      if (purchaseItems.length === 0) {
        showMessage('danger', 'Agrega al menos un item a la factura de compra.');
        return;
      }

      apiFetch('api/compras.php', {
        method: 'POST',
        body: JSON.stringify({
          fechaCompra: data.fechaCompra,
          facturaNumero: data.facturaNumero,
          proveedorNit: data.proveedorNit,
          proveedorNombre: data.proveedorNombre,
          items: purchaseItems.map(function (item) {
            return { productoId: item.productoId, cantidad: item.cantidad, costoUnitario: item.costoUnitario };
          })
        })
      }).then(function (body) {
        showMessage('success', body.message + ' Folio: ' + body.folio);
        event.target.reset();
        purchaseItems = [];
        renderPurchaseItems();
        return Promise.all([loadProducts(), loadPurchases()]);
      }).catch(function (error) {
        showMessage('danger', error.message);
      });
    });

    byId('userForm').addEventListener('submit', function (event) {
      event.preventDefault();
      apiFetch('api/usuarios.php', { method: 'POST', body: JSON.stringify(formToObject(event.target)) }).then(function (body) {
        showMessage('success', body.message || 'Usuario creado.');
        event.target.reset();
      }).catch(function (error) {
        showMessage('danger', error.message + ' | Si persiste, abre api/usuarios.php en Network para ver detalle.');
      });
    });

    byId('bankForm').addEventListener('submit', function (event) {
      event.preventDefault();
      var data = formToObject(event.target);
      data.accion = 'banco';
      apiFetch('api/bancos.php', { method: 'POST', body: JSON.stringify(data) }).then(function (body) {
        showMessage('success', body.message || 'Banco creado.');
        event.target.reset();
        return loadBanks();
      }).catch(function (error) {
        showMessage('danger', error.message);
      });
    });

    byId('bankPaymentForm').addEventListener('submit', function (event) {
      event.preventDefault();
      var data = formToObject(event.target);
      data.accion = 'pago';
      data.tipo = 'DEPOSITO';
      apiFetch('api/bancos.php', { method: 'POST', body: JSON.stringify(data) }).then(function (body) {
        showMessage('success', body.message || 'Pago registrado.');
        event.target.reset();
        return loadBanks();
      }).catch(function (error) {
        showMessage('danger', error.message);
      });
    });

    Array.prototype.forEach.call(document.querySelectorAll('.inventory-filter'), function (button) {
      button.addEventListener('click', function () {
        loadInventory(button.dataset.filter).catch(function (error) {
          showMessage('danger', error.message);
        });
      });
    });

    byId('reportForm').addEventListener('submit', function (event) {
      event.preventDefault();
      var data = formToObject(event.target);
      var params = new URLSearchParams();
      if (data.desde) {
        params.set('desde', data.desde);
      }
      if (data.hasta) {
        params.set('hasta', data.hasta);
      }
      apiFetch('api/reportes.php?' + params.toString()).then(function (body) {
        var v = body.ventas || {};
        var methods = (body.por_metodo_pago || []).map(function (m) { return m.metodo_pago + ': ' + m.total; }).join(' | ');
        byId('salesReport').textContent = 'Ventas: ' + v.cantidad_ventas + ' | Total vendido: ' + v.total_vendido + ' | Descuentos: ' + v.descuentos + (methods ? ' | Pagos: ' + methods : '');
      }).catch(function (error) {
        showMessage('danger', error.message);
      });
    });
  }

  var dashCharts = {};

  function fmt(value) {
    return 'Q ' + Number(value || 0).toLocaleString('es-GT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function renderDashboard(data) {
    // KPI hoy
    byId('kpiVentasHoy').textContent = fmt(data.hoy.total);
    byId('kpiVentasHoyCant').textContent = data.hoy.cantidad + ' ventas';

    // KPI mes
    byId('kpiVentasMes').textContent = fmt(data.mes.total);
    byId('kpiVentasMesCant').textContent = data.mes.cantidad + ' ventas | Desc.: ' + fmt(data.mes.descuentos);

    // KPI mes anterior
    var totalMes = Number(data.mes.total || 0);
    var totalAnt = Number(data.mes_anterior.total || 0);
    byId('kpiMesAnterior').textContent = fmt(totalAnt);
    var diff = totalAnt > 0 ? ((totalMes - totalAnt) / totalAnt * 100).toFixed(1) : null;
    var diffEl = byId('kpiMesAnteriorDiff');
    if (diff !== null) {
      diffEl.textContent = (diff >= 0 ? '▲ +' : '▼ ') + diff + '% vs mes ant.';
      diffEl.style.color = diff >= 0 ? '#198754' : '#dc3545';
    } else {
      diffEl.textContent = 'Sin datos mes anterior';
    }

    // KPI alertas
    var sinStock = Number(data.inventario.sin_stock || 0);
    var bajoMin = Number(data.inventario.bajo_minimo || 0);
    byId('kpiAlertas').textContent = sinStock + bajoMin;
    byId('kpiAlertasSub').textContent = sinStock + ' sin stock · ' + bajoMin + ' bajo mínimo';

    // Gráfica 7 días
    var dias = [];
    var totalesDias = [];
    var cantidadesDias = [];
    // Rellenar los 7 días aunque no haya ventas
    for (var i = 6; i >= 0; i--) {
      var d = new Date();
      d.setDate(d.getDate() - i);
      var key = d.toISOString().slice(0, 10);
      dias.push(key.slice(5)); // MM-DD
      var found = (data.ultimos_7_dias || []).find(function (r) { return r.dia === key; });
      totalesDias.push(found ? Number(found.total) : 0);
      cantidadesDias.push(found ? Number(found.cantidad) : 0);
    }

    var ctx7 = byId('chartVentas7Dias').getContext('2d');
    if (dashCharts.ventas7) { dashCharts.ventas7.destroy(); }
    dashCharts.ventas7 = new Chart(ctx7, {
      type: 'bar',
      data: {
        labels: dias,
        datasets: [
          { label: 'Total vendido (Q)', data: totalesDias, backgroundColor: 'rgba(13,110,253,.7)', borderRadius: 4, yAxisID: 'y' },
          { label: 'Cant. ventas', data: cantidadesDias, type: 'line', borderColor: '#198754', backgroundColor: 'rgba(25,135,84,.15)', tension: .3, yAxisID: 'y1' }
        ]
      },
      options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        scales: {
          y: { beginAtZero: true, ticks: { callback: function (v) { return 'Q' + v.toLocaleString('es-GT'); } } },
          y1: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false } }
        },
        plugins: { legend: { position: 'bottom' } }
      }
    });

    // Gráfica método de pago (doughnut)
    var ctxPago = byId('chartMetodoPago').getContext('2d');
    if (dashCharts.metodo) { dashCharts.metodo.destroy(); }
    var metodos = (data.por_metodo_pago || []);
    dashCharts.metodo = new Chart(ctxPago, {
      type: 'doughnut',
      data: {
        labels: metodos.map(function (m) { return m.metodo_pago; }),
        datasets: [{ data: metodos.map(function (m) { return Number(m.total); }), backgroundColor: ['#0d6efd','#198754','#ffc107','#dc3545','#6f42c1','#0dcaf0'] }]
      },
      options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });

    // Top productos
    var tbody = byId('dashTopProductos');
    if (!data.top_productos || !data.top_productos.length) {
      tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">Sin ventas este mes</td></tr>';
    } else {
      tbody.innerHTML = data.top_productos.map(function (p, i) {
        return '<tr><td><span class="badge bg-secondary me-1">' + (i + 1) + '</span>' + p.nombre + '<br><small class="text-muted">' + p.sku + '</small></td><td class="text-end">' + Number(p.unidades).toLocaleString('es-GT') + '</td><td class="text-end">' + fmt(p.total) + '</td></tr>';
      }).join('');
    }

    // Últimas ventas
    var tbodyV = byId('dashUltimasVentas');
    if (!data.ultimas_ventas || !data.ultimas_ventas.length) {
      tbodyV.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Sin ventas registradas</td></tr>';
    } else {
      tbodyV.innerHTML = data.ultimas_ventas.map(function (v) {
        var fecha = v.fecha ? v.fecha.slice(0, 16) : '';
        return '<tr><td><span class="font-monospace small">' + v.folio + '</span><br><small class="text-muted">' + fecha + '</small></td><td class="small">' + (v.usuario_nombre || '') + '</td><td><span class="badge text-bg-secondary">' + v.metodo_pago + '</span></td><td class="text-end fw-semibold">' + fmt(v.total) + '</td></tr>';
      }).join('');
    }
  }

  function loadDashboard() {
    return apiFetch('api/dashboard.php').then(function (data) {
      renderDashboard(data);
    }).catch(function (error) {
      showMessage('danger', 'Dashboard: ' + error.message);
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
    initBusinessModules();

    Array.prototype.forEach.call(document.querySelectorAll('[data-module]'), function (element) {
      element.addEventListener('click', function (event) {
        event.preventDefault();
        if (token) {
          var moduleName = element.getAttribute('data-module');
          if (['productos', 'compras', 'usuarios', 'bancos'].indexOf(moduleName) !== -1 && currentRole() !== 'ADMINISTRADOR') {
            showMessage('warning', 'Tu rol VENDEDOR no tiene permiso para este módulo.');
            return;
          }
          showModule(moduleName);
          if (moduleName === 'dashboard') {
            loadDashboard();
          }
        }
      });
    });

    var refreshBtn = byId('dashboardRefreshBtn');
    if (refreshBtn) {
      refreshBtn.addEventListener('click', function () {
        loadDashboard();
      });
    }

    byId('logoutBtn').addEventListener('click', function () {
      token = null;
      window.localStorage.removeItem('token');
      window.localStorage.removeItem('currentUser');
      currentUser = null;
      setLoggedIn(false);
      showMessage('info', 'Sesión cerrada.');
    });

    setLoggedIn(Boolean(token));
    applyRolePermissions();
    if (token) {
      showModule('menu');
    }
    renderCart();
    renderPurchaseItems();
    if (token) {
      loadProducts().catch(function () {});
      loadPurchases().catch(function () {});
      loadBanks().catch(function () {});
      loadInventory().catch(function () {});
    }
    showMessage('secondary', 'Aplicación cargada. Si no puedes entrar, pulsa "Probar conexión".');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
}());
