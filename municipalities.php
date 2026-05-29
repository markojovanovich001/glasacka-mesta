<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Општине - Glasačka Mesta</title>
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
            background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%);
            color: white;
            padding: 30px;
        }
        
        .content-section {
            padding: 30px;
        }
        
        .badge-status {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.85rem;
        }
        
        table.dataTable tbody tr {
            transition: all 0.2s;
        }
        
        table.dataTable tbody tr:hover {
            background-color: #f8f9fa;
            transform: scale(1.01);
        }
        
        .action-btn {
            padding: 4px 8px;
            font-size: 0.85rem;
            margin: 2px;
        }
        
        #municipalitiesTable {
            width: 100% !important;
        }
        
        #municipalitiesTable th,
        #municipalitiesTable td {
            white-space: nowrap;
        }
        
        #municipalitiesTable th:first-child,
        #municipalitiesTable td:first-child {
            min-width: 200px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="main-card">
            <div class="header-section">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1><i class="bi bi-geo-alt"></i> Управљање Локацијама</h1>
                        <p class="mb-0">Преглед свих локација (градова и општина) са статусима</p>
                    </div>
                    <a href="index.php" class="btn btn-light">
                        <i class="bi bi-arrow-left"></i> Назад
                    </a>
                </div>
            </div>

            <div class="content-section">
                <div class="table-responsive">
                    <table id="municipalitiesTable" class="table table-hover table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>Назив</th>
                                <th>Гласачка Места</th>
                                <th>Активна</th>
                                <th>Преузето</th>
                                <th>Парсирано</th>
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

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            const table = $('#municipalitiesTable').DataTable({
                ajax: {
                    url: 'api/get_municipalities.php',
                    dataSrc: 'data'
                },
                columns: [
                    { 
                        data: 'name',
                        render: function(data, type, row) {
                            return `<strong>${data}</strong><br><small class="text-muted">${row.slug}</small>`;
                        }
                    },
                    { 
                        data: 'places_count',
                        className: 'text-center',
                        render: function(data) {
                            return data > 0 ? `<span class="badge bg-primary">${data}</span>` : '-';
                        }
                    },
                    { 
                        data: 'active_count',
                        className: 'text-center',
                        render: function(data) {
                            return data > 0 ? `<span class="badge bg-success">${data}</span>` : '-';
                        }
                    },
                    { 
                        data: 'downloaded_at',
                        render: function(data) {
                            if (data) {
                                const date = new Date(data);
                                return `<small>${date.toLocaleDateString('sr-RS')}</small>`;
                            }
                            return '<span class="badge bg-secondary">Не</span>';
                        }
                    },
                    { 
                        data: 'parsed_at',
                        render: function(data) {
                            if (data) {
                                const date = new Date(data);
                                return `<small>${date.toLocaleDateString('sr-RS')}</small>`;
                            }
                            return '<span class="badge bg-secondary">Не</span>';
                        }
                    },
                    { 
                        data: 'active',
                        className: 'text-center',
                        render: function(data) {
                            return data == 1 
                                ? '<span class="badge bg-success badge-status">Активна</span>' 
                                : '<span class="badge bg-danger badge-status">Неактивна</span>';
                        }
                    },
                    {
                        data: null,
                        className: 'text-center',
                        render: function(data, type, row) {
                            let buttons = '';
                            
                            if (row.places_count > 0) {
                                buttons += `<a href="voting_places.php?municipality=${row.id}" class="btn btn-sm btn-primary action-btn">
                                    <i class="bi bi-eye"></i> Места
                                </a>`;
                            }
                            
                            if (row.doc_url) {
                                buttons += `<a href="${row.doc_url}" target="_blank" class="btn btn-sm btn-info action-btn">
                                    <i class="bi bi-download"></i> DOC
                                </a>`;
                            }
                            
                            return buttons || '-';
                        }
                    }
                ],
                order: [[0, 'asc']],
                pageLength: 25,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/sr.json',
                    search: 'Претрага:',
                    lengthMenu: 'Прикажи _MENU_ локација',
                    info: 'Приказано _START_ до _END_ од _TOTAL_ локација',
                    infoEmpty: 'Нема података',
                    infoFiltered: '(филтрирано од _MAX_ укупно)',
                    paginate: {
                        first: 'Прва',
                        last: 'Последња',
                        next: 'Следећа',
                        previous: 'Претходна'
                    }
                }
            });
            
            // Refresh every 30 seconds
            setInterval(function() {
                table.ajax.reload(null, false);
            }, 30000);
        });
    </script>
</body>
</html>
