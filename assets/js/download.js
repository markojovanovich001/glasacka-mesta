/**
 * Download Manager JavaScript
 * Handles UI interactions for downloading municipalities
 */

class DownloadManager {
    constructor() {
        this.isRunning = false;
        this.progressInterval = null;
        this.currentCount = 0;
        this.totalCount = 0;
        this.mode = 'new';
        
        this.initEventHandlers();
    }
    
    initEventHandlers() {
        $('#btnFetchMunicipalities').click(() => this.fetchMunicipalities());
        $('#btnStart').click(() => this.startDownload());
        $('#btnPause').click(() => this.pauseDownload());
        $('#btnResume').click(() => this.resumeDownload());
        $('#btnStop').click(() => this.stopDownload());
        $('#btnClearData').click(() => this.clearData());
    }
    
    log(message, type = 'info') {
        const timestamp = new Date().toLocaleTimeString('sr-RS');
        const colors = {
            info: 'text-primary',
            success: 'text-success',
            error: 'text-danger',
            warning: 'text-warning'
        };
        
        const html = `<div class="${colors[type] || colors.info}">
            <small>${timestamp}</small> ${message}
        </div>`;
        
        $('#logContainer').prepend(html);
        
        // Keep only last 100 entries
        const children = $('#logContainer').children();
        if (children.length > 100) {
            children.slice(100).remove();
        }
    }
    
    saveProgress(current, total, message, type = 'info', paused = false) {
        const percentage = total > 0 ? Math.round((current / total) * 100) : 0;
        
        $('#progressBar')
            .css('width', percentage + '%')
            .attr('aria-valuenow', current)
            .attr('aria-valuemax', total)
            .text(percentage + '%');
        
        $('#progressText').text(message);
        $('#progressStats').text(`${current} / ${total}`);
        
        // Save to server
        $.post('api/save_progress.php', {
            current: current,
            total: total,
            percentage: percentage,
            message: message,
            type: type,
            paused: paused
        });
    }
    
    updateProgress() {
        $.get('download.php?action=get_progress', (data) => {
            if (data.current !== undefined) {
                const percentage = data.percentage || 0;
                
                $('#progressBar')
                    .css('width', percentage + '%')
                    .text(percentage + '%');
                
                $('#progressText').text(data.message || '');
                $('#progressStats').text(`${data.current || 0} / ${data.total || 0}`);
                
                if (data.paused) {
                    $('#btnPause').prop('disabled', true);
                    $('#btnResume').prop('disabled', false);
                }
            }
        });
    }
    
    startProgressTracking() {
        if (!this.progressInterval) {
            this.progressInterval = setInterval(() => this.updateProgress(), 1000);
        }
    }
    
    stopProgressTracking() {
        if (this.progressInterval) {
            clearInterval(this.progressInterval);
            this.progressInterval = null;
        }
        this.isRunning = false;
        $('#btnStart').prop('disabled', false);
        $('#btnPause, #btnResume, #btnStop').prop('disabled', true);
    }
    
    fetchMunicipalities() {
        this.log('Учитавам локације са RIK сајта...', 'info');
        $('#btnFetchMunicipalities').prop('disabled', true);
        
        $.get('download.php?action=fetch_municipalities', (data) => {
            if (data.success) {
                this.log(`✅ Успешно! Учитано: ${data.total}, Сачувано: ${data.saved}, Ажурирано: ${data.updated}`, 'success');
                $('#btnStart').prop('disabled', false);
            } else {
                this.log(`❌ Грешка: ${data.message}`, 'error');
            }
            $('#btnFetchMunicipalities').prop('disabled', false);
        }).fail(() => {
            this.log('❌ Грешка у комуникацији са сервером', 'error');
            $('#btnFetchMunicipalities').prop('disabled', false);
        });
    }
    
    startDownload() {
        this.mode = $('input[name="downloadMode"]:checked').val();
        this.log(`Покрећем преузимање (режим: ${this.mode})...`, 'info');
        
        this.isRunning = true;
        $('#btnStart').prop('disabled', true);
        $('#btnPause, #btnStop').prop('disabled', false);
        $('#progressBar').removeClass('bg-danger bg-success').addClass('bg-info');
        
        // Get initial stats
        $.get('download.php?action=get_download_stats', (stats) => {
            this.currentCount = 0;
            this.totalCount = this.mode === 'all' ? stats.total : stats.remaining;
            this.saveProgress(0, this.totalCount, 'Започињем преузимање...', 'info');
            this.startProgressTracking();
            this.downloadNext();
        });
    }
    
    downloadNext() {
        // Check if we should stop
        $.get('download.php?action=control&cmd=status', (control) => {
            if (control.action === 'stop') {
                this.saveProgress(this.currentCount, this.totalCount, 'Заустављено од стране корисника', 'warning');
                this.log('⏹️ Заустављено', 'warning');
                this.stopProgressTracking();
                return;
            }
            
            if (control.action === 'pause') {
                this.saveProgress(this.currentCount, this.totalCount, 'ПАУЗИРАНО', 'info', true);
                setTimeout(() => this.downloadNext(), 2000);
                return;
            }
            
            // Get next municipality
            $.get(`download.php?action=get_next_municipality&mode=${this.mode}`, (data) => {
                if (!data.success) {
                    // No more municipalities
                    this.saveProgress(this.totalCount, this.totalCount, 'Завршено!', 'success');
                    this.log(`✅ Завршено! Обрађено: ${this.currentCount} локација`, 'success');
                    $('#progressBar').removeClass('bg-info').addClass('bg-success');
                    this.stopProgressTracking();
                    return;
                }
                
                const mun = data.municipality;
                this.currentCount++;
                
                this.saveProgress(this.currentCount, this.totalCount, `Преузимам: ${mun.name}`, 'info');
                this.log(`📥 ${this.currentCount}/${this.totalCount}: ${mun.name}`, 'info');
                
                // Download this municipality
                $.get(`download.php?action=download_one&id=${mun.id}`, (result) => {
                    if (result.success) {
                        this.log(`✓ ${mun.name} - ${(result.size / 1024).toFixed(1)} KB`, 'success');
                    } else {
                        this.log(`✗ ${mun.name} - ${result.message}`, 'error');
                    }
                    
                    // Continue with next
                    setTimeout(() => this.downloadNext(), 1500);
                    
                }).fail(() => {
                    this.log(`✗ ${mun.name} - грешка сервера`, 'error');
                    setTimeout(() => this.downloadNext(), 3000);
                });
                
            }).fail(() => {
                this.log('❌ Грешка при добављању следеће локације', 'error');
                this.stopProgressTracking();
            });
        });
    }
    
    pauseDownload() {
        $.get('download.php?action=control&cmd=pause', () => {
            this.log('⏸️ Паузирано', 'info');
            $('#btnPause').prop('disabled', true);
            $('#btnResume').prop('disabled', false);
        });
    }
    
    resumeDownload() {
        $.get('download.php?action=control&cmd=run', () => {
            this.log('▶️ Настављам...', 'info');
            $('#btnPause').prop('disabled', false);
            $('#btnResume').prop('disabled', true);
        });
    }
    
    stopDownload() {
        $.get('download.php?action=control&cmd=stop', () => {
            this.log('⏹️ Заустављено', 'warning');
            this.stopProgressTracking();
        });
    }
    
    clearData() {
        if (!confirm('Да ли сте сигурни да желите да обришете све податке?\n\nОво ће обрисати:\n- Све општине из базе\n- Сва гласачка места\n- Све преузете фајлове\n\nОва акција се не може опозвати!')) {
            return;
        }
        
        this.log('Бришем све податке...', 'warning');
        $('#btnClearData').prop('disabled', true);
        
        $.get('download.php?action=clear_data', (data) => {
            if (data.success) {
                this.log('✅ Сви подаци су обрисани', 'success');
                $('#progressBar').css('width', '0%').text('0%');
                $('#progressText').text('');
                $('#progressStats').text('0 / 0');
                $('#logContainer').empty();
                $('#btnStart').prop('disabled', true);
            } else {
                this.log(`❌ Грешка: ${data.message}`, 'error');
            }
            $('#btnClearData').prop('disabled', false);
        }).fail(() => {
            this.log('❌ Грешка у комуникацији са сервером', 'error');
            $('#btnClearData').prop('disabled', false);
        });
    }
}

// Initialize on document ready
$(document).ready(function() {
    window.downloadManager = new DownloadManager();
});
