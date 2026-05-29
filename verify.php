<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Верификација - Glasačka Mesta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
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
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 30px;
        }
        
        .content-section {
            padding: 30px;
        }
        
        .stats-card {
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
        }
        
        .stats-number {
            font-size: 3rem;
            font-weight: 700;
        }
        
        .action-card {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        
        .action-card:hover {
            border-color: #4facfe;
            box-shadow: 0 5px 20px rgba(79, 172, 254, 0.2);
        }
        
        .verification-item {
            padding: 15px;
            border-left: 4px solid #e9ecef;
            margin-bottom: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .verification-item.success {
            border-left-color: #28a745;
            background: #f0fff4;
        }
        
        .verification-item.warning {
            border-left-color: #ffc107;
            background: #fffbf0;
        }
        
        .verification-item.error {
            border-left-color: #dc3545;
            background: #fff5f5;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="main-card">
            <div class="header-section">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1><i class="bi bi-shield-check"></i> Верификација и Провера</h1>
                        <p class="mb-0">Систем за проверу података и валидацију</p>
                    </div>
                    <a href="index.php" class="btn btn-light">
                        <i class="bi bi-arrow-left"></i> Назад
                    </a>
                </div>
            </div>

            <div class="content-section">
                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-number" id="statTotal">0</div>
                            <div>Укупно Места</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%);">
                            <div class="stats-number" id="statVerified">0</div>
                            <div>Верификовано</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <div class="stats-number" id="statUnverified">0</div>
                            <div>Неверификовано</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, #c31432 0%, #240b36 100%);">
                            <div class="stats-number" id="statInactive">0</div>
                            <div>Неактивно</div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="action-card">
                            <h4><i class="bi bi-file-check"></i> Провера Података</h4>
                            <p>Провери све општине и гласачка места за недостајуће или неисправне податке</p>
                            <button class="btn btn-primary w-100" id="btnCheckData">
                                <i class="bi bi-play-circle"></i> Покрени Проверу
                            </button>
                            <div id="checkResults" class="mt-3" style="display: none;"></div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="action-card">
                            <h4><i class="bi bi-arrow-clockwise"></i> Рехек са RIK-а</h4>
                            <p>Поново преузми и упореди податке са RIK сајтом</p>
                            <button class="btn btn-warning w-100" id="btnRecheck">
                                <i class="bi bi-cloud-download"></i> Рехек Података
                            </button>
                            <div id="recheckResults" class="mt-3" style="display: none;"></div>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="action-card">
                            <h4><i class="bi bi-list-check"></i> Масовна Верификација</h4>
                            <p>Означи сва гласачка места као верификована</p>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i> Ова акција ће означити сва активна места као верификована.
                            </div>
                            <button class="btn btn-success w-100" id="btnBulkVerify">
                                <i class="bi bi-check-all"></i> Верификуј Све
                            </button>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="action-card">
                            <h4><i class="bi bi-file-earmark-spreadsheet"></i> Извоз Извештаја</h4>
                            <p>Генериши детаљан извештај о свим гласачким местима</p>
                            <div class="d-grid gap-2">
                                <button class="btn btn-info" id="btnExportJSON">
                                    <i class="bi bi-file-code"></i> JSON Формат
                                </button>
                                <button class="btn btn-info" id="btnExportCSV">
                                    <i class="bi bi-file-earmark-text"></i> CSV Формат
                                </button>
                                <button class="btn btn-info" id="btnExportSQL">
                                    <i class="bi bi-database"></i> SQL за WordPress
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="mt-4">
                    <h4><i class="bi bi-clock-history"></i> Последње Активности</h4>
                    <div id="activityLog" class="mt-3">
                        <p class="text-muted">Учитавање...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            loadStats();
            loadActivityLog();
        });

        function loadStats() {
            $.get('api/get_stats.php', function(data) {
                if (data.success) {
                    $('#statTotal').text(data.voting_places || 0);
                    $('#statVerified').text(data.verified_places || 0);
                    $('#statUnverified').text((data.voting_places || 0) - (data.verified_places || 0));
                    $('#statInactive').text((data.voting_places || 0) - (data.active_places || 0));
                }
            });
        }

        function loadActivityLog() {
            $.get('api/get_activity_log.php?limit=10', function(data) {
                if (data.success) {
                    let html = '';
                    if (data.data.length > 0) {
                        data.data.forEach(item => {
                            const date = new Date(item.created_at);
                            html += `
                                <div class="verification-item">
                                    <div class="d-flex justify-content-between">
                                        <strong>${item.action}</strong>
                                        <small class="text-muted">${date.toLocaleString('sr-RS')}</small>
                                    </div>
                                    ${item.details ? `<small class="text-muted">${item.details}</small>` : ''}
                                </div>
                            `;
                        });
                    } else {
                        html = '<p class="text-muted">Нема евидентираних активности</p>';
                    }
                    $('#activityLog').html(html);
                }
            });
        }

        $('#btnCheckData').click(function() {
            $(this).prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Проверавам...');
            
            $.get('api/verify_data.php', function(data) {
                $('#checkResults').show();
                let html = '<h5 class="mt-3">Резултати Провере:</h5>';
                
                if (data.success) {
                    html += `<div class="verification-item success">
                        <i class="bi bi-check-circle"></i> <strong>Укупно општине:</strong> ${data.municipalities_total}
                    </div>`;
                    html += `<div class="verification-item success">
                        <i class="bi bi-check-circle"></i> <strong>Укупно гласачка места:</strong> ${data.places_total}
                    </div>`;
                    
                    if (data.missing_data > 0) {
                        html += `<div class="verification-item warning">
                            <i class="bi bi-exclamation-triangle"></i> <strong>Места са непотпуним подацима:</strong> ${data.missing_data}
                        </div>`;
                    }
                    
                    if (data.municipalities_no_places > 0) {
                        html += `<div class="verification-item warning">
                            <i class="bi bi-exclamation-triangle"></i> <strong>Општине без гласачких места:</strong> ${data.municipalities_no_places}
                        </div>`;
                    }
                    
                    if (data.missing_data === 0 && data.municipalities_no_places === 0) {
                        html += `<div class="verification-item success">
                            <i class="bi bi-check-circle-fill"></i> <strong>Сви подаци су комплетни!</strong>
                        </div>`;
                    }
                } else {
                    html += `<div class="verification-item error">
                        <i class="bi bi-x-circle"></i> Грешка: ${data.error}
                    </div>`;
                }
                
                $('#checkResults').html(html);
                $('#btnCheckData').prop('disabled', false).html('<i class="bi bi-play-circle"></i> Покрени Проверу');
                loadStats();
            });
        });

        $('#btnRecheck').click(function() {
            if (confirm('Да ли сте сигурни да желите да поново преузмете све податке са RIK сајта?')) {
                window.location.href = 'download.php?start=1&mode=all';
            }
        });

        $('#btnBulkVerify').click(function() {
            if (confirm('Да ли сте сигурни да желите да означите сва активна места као верификована?')) {
                $.post('api/bulk_verify.php', function(data) {
                    if (data.success) {
                        alert(`Успешно верификовано ${data.count} места!`);
                        loadStats();
                        loadActivityLog();
                    } else {
                        alert('Грешка: ' + data.error);
                    }
                });
            }
        });

        $('#btnExportJSON').click(function() {
            window.location.href = 'api/export.php?format=json';
        });

        $('#btnExportCSV').click(function() {
            window.location.href = 'api/export.php?format=csv';
        });

        $('#btnExportSQL').click(function() {
            window.location.href = 'api/export.php?format=sql';
        });
    </script>
</body>
</html>
