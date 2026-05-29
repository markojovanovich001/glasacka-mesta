<?php
/**
 * Download Manager - New Clean Version with MVC
 * 
 * @version 2.0
 * @date 2026-02-01
 */

require_once 'config.php';
require_once 'controllers/DownloadController.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ==================== API HANDLERS ====================

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    $controller = new DownloadController();
    
    switch ($_GET['action']) {
        case 'fetch_municipalities':
            $result = $controller->fetchMunicipalities();
            if ($result['success']) {
                $save = $controller->saveMunicipalities($result['municipalities']);
                echo json_encode([
                    'success' => true,
                    'total' => count($result['municipalities']),
                    'saved' => $save['saved'],
                    'updated' => $save['updated']
                ]);
            } else {
                echo json_encode($result);
            }
            break;
            
        case 'get_progress':
            if (file_exists(PROGRESS_JSON)) {
                echo file_get_contents(PROGRESS_JSON);
            } else {
                echo json_encode(['current' => 0, 'total' => 0, 'percentage' => 0]);
            }
            break;
            
        case 'control':
            $cmd = $_GET['cmd'] ?? 'status';
            if ($cmd === 'status') {
                echo json_encode(checkControl());
            } else {
                setControl($cmd);
                echo json_encode(['success' => true, 'action' => $cmd]);
            }
            break;
            
        case 'get_next_municipality':
            $mode = $_GET['mode'] ?? 'new';
            $db = getDB();
            $municipalityModel = new Municipality($db);
            $mun = $municipalityModel->getNextToDownload($mode);
            
            if ($mun) {
                echo json_encode(['success' => true, 'municipality' => $mun]);
            } else {
                echo json_encode(['success' => false, 'message' => 'No more municipalities']);
            }
            break;
            
        case 'download_one':
            $id = intval($_GET['id'] ?? 0);
            
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'Invalid ID']);
                break;
            }
            
            $db = getDB();
            $municipalityModel = new Municipality($db);
            $mun = $municipalityModel->getById($id);
            
            if (!$mun) {
                echo json_encode(['success' => false, 'message' => 'Municipality not found']);
                break;
            }
            
            $result = $controller->downloadDocFile($mun['id'], $mun['doc_url']);
            
            if ($result['success']) {
                $controller->parseDocFile($mun['id']);
                echo json_encode([
                    'success' => true,
                    'municipality' => $mun['name'],
                    'file' => $result['file'],
                    'size' => $result['size']
                ]);
            } else {
                echo json_encode($result);
            }
            break;
            
        case 'get_download_stats':
            $db = getDB();
            $municipalityModel = new Municipality($db);
            echo json_encode(['success' => true] + $municipalityModel->getStats());
            break;
            
        case 'clear_data':
            echo json_encode($controller->clearAllData());
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
    exit;
}

/**
 * Helper functions
 */
function checkControl() {
    if (file_exists(CONTROL_JSON)) {
        $control = json_decode(file_get_contents(CONTROL_JSON), true);
        return $control ?: ['action' => 'run'];
    }
    return ['action' => 'run'];
}

function setControl($action) {
    file_put_contents(CONTROL_JSON, json_encode([
        'action' => $action,
        'timestamp' => time(),
        'datetime' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE));
}

// ==================== VIEW ====================
?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Преузимање - Glasačka Mesta</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
        }
        
        .control-section {
            padding: 30px;
        }
        
        .btn-group-custom {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .btn-custom {
            flex: 1;
            min-width: 150px;
        }
        
        .progress-custom {
            height: 30px;
            font-size: 14px;
            font-weight: bold;
        }
        
        .log-container {
            max-height: 400px;
            overflow-y: auto;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="main-card">
            <div class="header-section">
                <h1 class="mb-0"><i class="bi bi-cloud-download"></i> Преузимање докумената</h1>
                <p class="mb-0 mt-2">Аутоматско преузимање и парсирање DOC фајлова са RIK сајта</p>
            </div>
            
            <div class="control-section">
                <!-- Step 1: Fetch Municipalities -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-1-circle"></i> Корак 1: Учитај локације</h5>
                        <p class="text-muted">Преузми листу општина/градова са RIK сајта</p>
                        <button class="btn btn-primary btn-custom" id="btnFetchMunicipalities">
                            <i class="bi bi-download"></i> Учитај локације
                        </button>
                    </div>
                </div>
                
                <!-- Step 2: Download Mode -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-2-circle"></i> Корак 2: Режим преузимања</h5>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="downloadMode" id="modeNew" value="new" checked>
                            <label class="form-check-label" for="modeNew">
                                Само нове (није преузето)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="downloadMode" id="modeAll" value="all">
                            <label class="form-check-label" for="modeAll">
                                Све локације (поново преузми све)
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Step 3: Controls -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-3-circle"></i> Корак 3: Контрола</h5>
                        <div class="btn-group-custom">
                            <button class="btn btn-success btn-custom" id="btnStart" disabled>
                                <i class="bi bi-play-fill"></i> Покрени
                            </button>
                            <button class="btn btn-warning btn-custom" id="btnPause" disabled>
                                <i class="bi bi-pause-fill"></i> Паузирај
                            </button>
                            <button class="btn btn-info btn-custom" id="btnResume" disabled>
                                <i class="bi bi-skip-forward-fill"></i> Настави
                            </button>
                            <button class="btn btn-danger btn-custom" id="btnStop" disabled>
                                <i class="bi bi-stop-fill"></i> Заустави
                            </button>
                            <button class="btn btn-outline-danger btn-custom" id="btnClearData">
                                <i class="bi bi-trash"></i> Обриши све
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Progress -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Напредак</h5>
                        <div class="progress progress-custom mb-2">
                            <div class="progress-bar bg-info" role="progressbar" style="width: 0%" 
                                 aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" id="progressBar">0%</div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span id="progressText" class="text-muted"></span>
                            <span id="progressStats" class="text-muted">0 / 0</span>
                        </div>
                    </div>
                </div>
                
                <!-- Log -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Лог активности</h5>
                        <div class="log-container" id="logContainer">
                            <div class="text-muted">Чека се почетак...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-4">
            <a href="index.php" class="btn btn-light"><i class="bi bi-house"></i> Почетна</a>
            <a href="municipalities.php" class="btn btn-light"><i class="bi bi-geo-alt"></i> Локације</a>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/download.js"></script>
</body>
</html>
