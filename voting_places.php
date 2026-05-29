<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Гласачка Места - Управљање</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
            min-height: 100vh;
            padding: 30px 0;
        }
        
        .main-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 25px 70px rgba(0,0,0,0.4);
            overflow: hidden;
        }
        
        .header-section {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 30px;
        }
        
        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .content-section {
            padding: 30px;
        }
        
        .badge-active {
            background: #28a745;
        }
        
        .badge-inactive {
            background: #dc3545;
        }
        
        .badge-verified {
            background: #17a2b8;
        }
        
        .action-btn {
            padding: 4px 8px;
            font-size: 0.85rem;
            margin: 2px;
        }
        
        table.dataTable tbody tr.inactive {
            opacity: 0.6;
            background-color: #fff5f5;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="main-card">
            <div class="header-section">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1><i class="bi bi-geo-alt"></i> Гласачка Места</h1>
                        <p class="mb-0">Преглед и управљање гласачким местима по локацијама</p>
                    </div>
                    <div>
                        <a href="municipalities.php" class="btn btn-light me-2">
                            <i class="bi bi-geo-alt"></i> Локације
                        </a>
                        <a href="index.php" class="btn btn-light">
                            <i class="bi bi-arrow-left"></i> Назад
                        </a>
                    </div>
                </div>
            </div>

            <div class="filter-section">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Локација:</label>
                        <select id="filterMunicipality" class="form-select">
                            <option value="">Све локације</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Статус:</label>
                        <select id="filterStatus" class="form-select">
                            <option value="">Сви</option>
                            <option value="active">Само активна</option>
                            <option value="inactive">Само неактивна</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Верификација:</label>
                        <select id="filterVerified" class="form-select">
                            <option value="">Сви</option>
                            <option value="1">Верификована</option>
                            <option value="0">Неверификована</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="content-section">
                <div class="mb-3">
                    <button class="btn btn-success" id="btnExportActive">
                        <i class="bi bi-file-earmark-excel"></i> Експорт Активних
                    </button>
                    <button class="btn btn-info" id="btnExportAll">
                        <i class="bi bi-file-earmark-text"></i> Експорт Свих
                    </button>
                </div>

                <div class="table-responsive">
                    <table id="placesTable" class="table table-hover table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>Локација</th>
                                <th>Број</th>
                                <th>Назив</th>
                                <th>Адреса</th>
                                <th>Подручје</th>
                                <th>Статус</th>
                                <th>Акције</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Loaded via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Измени Гласачко Место</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editForm">
                        <input type="hidden" id="editId">
                        
                        <div class="mb-3">
                            <label class="form-label">Локација:</label>
                            <input type="text" id="editMunicipality" class="form-control" readonly>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Број Места:</label>
                                <input type="number" id="editNumber" class="form-control">
                            </div>
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Назив:</label>
                                <input type="text" id="editName" class="form-control">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Адреса:</label>
                            <input type="text" id="editAddress" class="form-control">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Подручје:</label>
                            <input type="text" id="editArea" class="form-control">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Адресе у Подручју:</label>
                            <textarea id="editAreaAddresses" class="form-control" rows="3"></textarea>
                            <small class="form-text text-muted">Унесите све адресе које спадају у ово гласачко место</small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="editActive">
                                    <label class="form-check-label" for="editActive">
                                        <strong>Активно</strong> <small class="text-muted">(приказује се на сајту)</small>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="editVerified">
                                    <label class="form-check-label" for="editVerified">
                                        <strong>Верификовано</strong> <small class="text-muted">(проверено)</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Напомене:</label>
                            <textarea id="editNotes" class="form-control" rows="2"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Откажи</button>
                    <button type="button" class="btn btn-primary" id="btnSaveChanges">
                        <i class="bi bi-save"></i> Сачувај Измене
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        let table;
        const editModal = new bootstrap.Modal(document.getElementById('editModal'));
        
        $(document).ready(function() {
            // Load municipalities for filter
            loadMunicipalities();
            
            // Get municipality from URL if specified
            const urlParams = new URLSearchParams(window.location.search);
            const municipalityId = urlParams.get('municipality');
            
            // Initialize DataTable
            table = $('#placesTable').DataTable({
                ajax: {
                    url: 'api/get_voting_places.php',
                    data: function(d) {
                        d.municipality_id = $('#filterMunicipality').val() || municipalityId;
                        d.active_only = $('#filterStatus').val() === 'active' ? '1' : '';
                    },
                    dataSrc: 'data'
                },
                columns: [
                    { 
                        data: 'municipality_name',
                        render: function(data) {
                            return `<strong>${data}</strong>`;
                        }
                    },
                    { 
                        data: 'place_number',
                        className: 'text-center',
                        render: function(data) {
                            return data ? `<span class="badge bg-secondary">${data}</span>` : '-';
                        }
                    },
                    { data: 'place_name' },
                    { data: 'address' },
                    { 
                        data: 'area',
                        render: function(data, type, row) {
                            let html = data || '-';
                            if (row.area_addresses) {
                                html += `<br><small class="text-muted">${row.area_addresses}</small>`;
                            }
                            return html;
                        }
                    },
                    { 
                        data: null,
                        className: 'text-center',
                        render: function(data, type, row) {
                            let badges = '';
                            badges += row.active == 1 
                                ? '<span class="badge badge-active me-1">Активно</span>' 
                                : '<span class="badge badge-inactive me-1">Неактивно</span>';
                            
                            if (row.verified == 1) {
                                badges += '<span class="badge badge-verified">Верификовано</span>';
                            }
                            
                            return badges;
                        }
                    },
                    {
                        data: null,
                        className: 'text-center',
                        render: function(data, type, row) {
                            return `
                                <button class="btn btn-sm btn-primary action-btn btn-edit" data-id="${row.id}">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-${row.active == 1 ? 'danger' : 'success'} action-btn btn-toggle" data-id="${row.id}" data-active="${row.active}">
                                    <i class="bi bi-${row.active == 1 ? 'x-circle' : 'check-circle'}"></i>
                                </button>
                            `;
                        }
                    }
                ],
                order: [[1, 'asc']],
                pageLength: 50,
                rowCallback: function(row, data) {
                    if (data.active == 0) {
                        $(row).addClass('inactive');
                    }
                },
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/sr.json',
                    search: 'Претрага:',
                    lengthMenu: 'Прикажи _MENU_ места',
                    info: 'Приказано _START_ до _END_ од _TOTAL_ места',
                    infoEmpty: 'Нема података',
                    paginate: {
                        first: 'Прва',
                        last: 'Последња',
                        next: 'Следећа',
                        previous: 'Претходна'
                    }
                }
            });
            
            // Set municipality filter if specified
            if (municipalityId) {
                setTimeout(() => {
                    $('#filterMunicipality').val(municipalityId);
                }, 500);
            }
            
            // Filter changes
            $('#filterMunicipality, #filterStatus, #filterVerified').change(function() {
                table.ajax.reload();
            });
            
            // Edit button
            $('#placesTable').on('click', '.btn-edit', function() {
                const id = $(this).data('id');
                loadPlaceForEdit(id);
            });
            
            // Toggle active/inactive
            $('#placesTable').on('click', '.btn-toggle', function() {
                const id = $(this).data('id');
                const active = $(this).data('active');
                togglePlaceStatus(id, active == 1 ? 0 : 1);
            });
            
            // Save changes
            $('#btnSaveChanges').click(function() {
                savePlaceChanges();
            });
        });
        
        function loadMunicipalities() {
            $.get('api/get_municipalities.php', function(data) {
                if (data.success) {
                    data.data.forEach(mun => {
                        $('#filterMunicipality').append(
                            `<option value="${mun.id}">${mun.name} (${mun.places_count || 0})</option>`
                        );
                    });
                }
            });
        }
        
        function loadPlaceForEdit(id) {
            $.get(`api/get_voting_places.php`, function(response) {
                if (response.success) {
                    const place = response.data.find(p => p.id == id);
                    if (place) {
                        $('#editId').val(place.id);
                        $('#editMunicipality').val(place.municipality_name);
                        $('#editNumber').val(place.place_number);
                        $('#editName').val(place.place_name);
                        $('#editAddress').val(place.address);
                        $('#editArea').val(place.area);
                        $('#editAreaAddresses').val(place.area_addresses);
                        $('#editActive').prop('checked', place.active == 1);
                        $('#editVerified').prop('checked', place.verified == 1);
                        $('#editNotes').val(place.notes);
                        
                        editModal.show();
                    }
                }
            });
        }
        
        function savePlaceChanges() {
            const data = {
                id: $('#editId').val(),
                place_number: $('#editNumber').val(),
                place_name: $('#editName').val(),
                address: $('#editAddress').val(),
                area: $('#editArea').val(),
                area_addresses: $('#editAreaAddresses').val(),
                active: $('#editActive').is(':checked') ? 1 : 0,
                verified: $('#editVerified').is(':checked') ? 1 : 0,
                notes: $('#editNotes').val()
            };
            
            $.ajax({
                url: 'api/update_place.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(data),
                success: function(response) {
                    if (response.success) {
                        editModal.hide();
                        table.ajax.reload();
                        alert('Успешно сачувано!');
                    } else {
                        alert('Грешка: ' + response.error);
                    }
                },
                error: function() {
                    alert('Грешка при комуникацији са сервером');
                }
            });
        }
        
        function togglePlaceStatus(id, newStatus) {
            if (confirm(`Да ли сте сигурни да желите да ${newStatus == 1 ? 'активирате' : 'деактивирате'} ово место?`)) {
                $.ajax({
                    url: 'api/update_place.php',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        id: id,
                        active: newStatus
                    }),
                    success: function(response) {
                        if (response.success) {
                            table.ajax.reload();
                        } else {
                            alert('Грешка: ' + response.error);
                        }
                    }
                });
            }
        }
    </script>
</body>
</html>
