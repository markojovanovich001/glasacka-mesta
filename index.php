<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Glasačka Mesta - Republika Srbija</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
            min-height: 100vh;
            padding: 30px 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 25px 70px rgba(0,0,0,0.4);
            overflow: hidden;
        }
        
        .header-section {
            background: linear-gradient(135deg, #c31432 0%, #240b36 100%);
            color: white;
            padding: 40px;
            position: relative;
            overflow: hidden;
        }
        
        .header-section::before {
            content: '🗳️';
            position: absolute;
            font-size: 200px;
            opacity: 0.1;
            right: -50px;
            top: -50px;
        }
        
        .header-section h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .header-section .subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .stats-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 30px;
            color: white;
            margin: 30px;
        }
        
        .stat-card {
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s;
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            background: rgba(255,255,255,0.3);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 10px 0;
        }
        
        .stat-label {
            font-size: 0.95rem;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .action-section {
            padding: 40px;
        }
        
        .action-card {
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            transition: all 0.3s;
            background: linear-gradient(to bottom, #ffffff 0%, #f8f9fa 100%);
        }
        
        .action-card:hover {
            border-color: #667eea;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.2);
            transform: translateY(-3px);
        }
        
        .action-card i {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: #667eea;
        }
        
        .action-card h4 {
            font-weight: 600;
            margin-bottom: 10px;
            color: #2c5364;
        }
        
        .action-card p {
            color: #6c757d;
            margin-bottom: 15px;
        }
        
        .btn-action {
            width: 100%;
            padding: 12px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .btn-primary-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
        }
        
        .btn-primary-custom:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-success-custom {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%);
            border: none;
            color: white;
        }
        
        .btn-success-custom:hover {
            background: linear-gradient(135deg, #a8e063 0%, #56ab2f 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(86, 171, 47, 0.4);
        }
        
        .btn-warning-custom {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border: none;
            color: white;
        }
        
        .btn-warning-custom:hover {
            background: linear-gradient(135deg, #f5576c 0%, #f093fb 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(245, 87, 108, 0.4);
        }
        
        .btn-info-custom {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            border: none;
            color: white;
        }
        
        .btn-info-custom:hover {
            background: linear-gradient(135deg, #00f2fe 0%, #4facfe 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(79, 172, 254, 0.4);
        }
        
        .footer-section {
            background: #2c3e50;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 0 0 20px 20px;
        }
        
        .alert-custom {
            border-radius: 10px;
            border-left: 5px solid;
        }
        
        .badge-custom {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .loading {
            animation: pulse 1.5s infinite;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="main-card">
            <!-- Header -->
            <div class="header-section">
                <h1><i class="bi bi-check2-square"></i> Glasačka Mesta</h1>
                <p class="subtitle">Republika Srbija - Sistem za upravljanje glasačkim mestima</p>
                <div class="mt-3">
                    <span class="badge bg-light text-dark me-2">
                        <i class="bi bi-database"></i> SQLite Database
                    </span>
                    <span class="badge bg-light text-dark me-2">
                        <i class="bi bi-bootstrap"></i> Bootstrap 5
                    </span>
                    <span class="badge bg-light text-dark">
                        <i class="bi bi-wordpress"></i> WordPress Ready
                    </span>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-container">
                <div class="row g-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <i class="bi bi-geo-alt"></i>
                            <div class="stat-number" id="stat-municipalities">0</div>
                            <div class="stat-label">Локације</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <i class="bi bi-check2-square"></i>
                            <div class="stat-number" id="stat-places">0</div>
                            <div class="stat-label">Гласачка Места</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <i class="bi bi-check-circle"></i>
                            <div class="stat-number" id="stat-active">0</div>
                            <div class="stat-label">Активна</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <i class="bi bi-shield-check"></i>
                            <div class="stat-number" id="stat-verified">0</div>
                            <div class="stat-label">Верификована</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="action-section">
                <div class="row g-4">
                    <!-- Download & Parse -->
                    <div class="col-md-6">
                        <div class="action-card">
                            <i class="bi bi-download"></i>
                            <h4>Преузимање Докумената</h4>
                            <p>Преузми DOC/DOCX фајлове са RIK сајта и аутоматски парсуј гласачка места</p>
                            <a href="download.php" class="btn btn-action btn-primary-custom">
                                <i class="bi bi-cloud-download"></i> Преузми и Парсуј
                            </a>
                        </div>
                    </div>

                    <!-- Locations -->
                    <div class="col-md-6">
                        <div class="action-card">
                            <i class="bi bi-geo-alt"></i>
                            <h4>Управљање Локацијама</h4>
                            <p>Преглед свих локација (градови и општине), статуси преузимања и парсирања докумената</p>
                            <a href="municipalities.php" class="btn btn-action btn-success-custom">
                                <i class="bi bi-list-ul"></i> Управљај Локацијама
                            </a>
                        </div>
                    </div>

                    <!-- Voting Places -->
                    <div class="col-md-6">
                        <div class="action-card">
                            <i class="bi bi-geo-alt"></i>
                            <h4>Гласачка Места</h4>
                            <p>Преглед, измена и управљање свим гласачким местима по општинама</p>
                            <a href="voting_places.php" class="btn btn-action btn-warning-custom">
                                <i class="bi bi-pencil-square"></i> Гласачка Места
                            </a>
                        </div>
                    </div>

                    <!-- Verification -->
                    <div class="col-md-6">
                        <div class="action-card">
                            <i class="bi bi-shield-check"></i>
                            <h4>Верификација</h4>
                            <p>Провера тачности података, рехек са RIK-а и валидација</p>
                            <a href="verify.php" class="btn btn-action btn-info-custom">
                                <i class="bi bi-check-all"></i> Верификуј Податке
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Quick Info -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="alert alert-info alert-custom border-info">
                            <h5><i class="bi bi-info-circle"></i> Брзи Водич</h5>
                            <ol class="mb-0">
                                <li><strong>Преузимање:</strong> Кликните на "Преузми и Парсуј" да преузмете све DOC фајлове са RIK сајта</li>
                                <li><strong>Парсирање:</strong> Систем аутоматски чита табеле из Word докумената</li>
                                <li><strong>Управљање:</strong> Прегледајте и измените гласачка места по потреби</li>
                                <li><strong>Верификација:</strong> Означите верификована места и деактивирајте она која више не важе</li>
                                <li><strong>Export:</strong> Експортујте базу за WordPress интеграцију</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <!-- System Status -->
                <div class="row mt-3">
                    <div class="col-md-4">
                        <div class="card border-success">
                            <div class="card-body text-center">
                                <i class="bi bi-database-check text-success" style="font-size: 2rem;"></i>
                                <h6 class="mt-2">База Података</h6>
                                <span class="badge bg-success" id="db-status">Спремна</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-primary">
                            <div class="card-body text-center">
                                <i class="bi bi-link-45deg text-primary" style="font-size: 2rem;"></i>
                                <h6 class="mt-2">RIK Конекција</h6>
                                <span class="badge bg-primary" id="rik-status">Доступна</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-info">
                            <div class="card-body text-center">
                                <i class="bi bi-folder-check text-info" style="font-size: 2rem;"></i>
                                <h6 class="mt-2">Складиште</h6>
                                <span class="badge bg-info" id="storage-status">OK</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="footer-section">
                <p class="mb-1"><i class="bi bi-code-slash"></i> Developed for RIK Integration - January 2026</p>
                <p class="mb-0">
                    <a href="reference/" class="text-white text-decoration-none me-3">
                        <i class="bi bi-folder"></i> Reference
                    </a>
                    <a href="README.md" class="text-white text-decoration-none">
                        <i class="bi bi-book"></i> Documentation
                    </a>
                </p>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Load statistics
        function loadStats() {
            fetch('api/get_stats.php')
                .then(response => response.json())
                .then(data => {
                    $('#stat-municipalities').text(data.municipalities || 0);
                    $('#stat-places').text(data.voting_places || 0);
                    $('#stat-active').text(data.active_places || 0);
                    $('#stat-verified').text(data.verified_places || 0);
                })
                .catch(error => {
                    console.error('Error loading stats:', error);
                });
        }

        // Check database status
        function checkDatabaseStatus() {
            fetch('api/health_check.php')
                .then(response => response.json())
                .then(data => {
                    if (data.database) {
                        $('#db-status').removeClass('bg-danger').addClass('bg-success').text('Спремна');
                    } else {
                        $('#db-status').removeClass('bg-success').addClass('bg-danger').text('Грешка');
                    }
                })
                .catch(error => {
                    $('#db-status').removeClass('bg-success').addClass('bg-warning').text('Непозната');
                });
        }

        // Initialize on load
        $(document).ready(function() {
            loadStats();
            checkDatabaseStatus();
            
            // Refresh stats every 30 seconds
            setInterval(loadStats, 30000);
        });
    </script>
</body>
</html>
