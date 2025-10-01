<?php
/**
 * Pulse Admin Interface
 * 
 * Admin interface for managing Pulse slides and footer messages
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Middleware\AuthMiddleware;
use Laguna\Integration\Utils\UrlHelper;

// Set timezone
date_default_timezone_set('America/New_York');

$config = require __DIR__ . '/../config/config.php';

// Require authentication and admin role
$auth = new AuthMiddleware();
$currentUser = $auth->requireAuth();
if (!$currentUser || $currentUser['role'] !== 'admin') {
    header('Location: ' . UrlHelper::url('access-denied.php'));
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $slidesFile = __DIR__ . '/pulse/json/slides.json';
    $footerFile = __DIR__ . '/pulse/json/footer-messages.json';
    $backupDir = __DIR__ . '/pulse/backups';
    
    // Create backup directory if it doesn't exist
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    try {
        switch ($_POST['action']) {
            case 'get_slides':
                $slides = file_exists($slidesFile) ? json_decode(file_get_contents($slidesFile), true) : [];
                if ($slides === null) {
                    throw new Exception('Invalid JSON in slides file');
                }
                echo json_encode(['success' => true, 'data' => $slides]);
                break;
                
            case 'save_slides':
                $slides = json_decode($_POST['slides'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Invalid JSON format for slides: ' . json_last_error_msg());
                }
                
                // Validate slide structure
                foreach ($slides as $index => $slide) {
                    if (!isset($slide['type']) || !isset($slide['duration'])) {
                        throw new Exception("Slide $index is missing required fields (type, duration)");
                    }
                    if (!is_numeric($slide['duration']) || $slide['duration'] <= 0) {
                        throw new Exception("Slide $index has invalid duration");
                    }
                }
                
                // Create backup before saving
                if (file_exists($slidesFile)) {
                    $backupFile = $backupDir . '/slides_' . date('Y-m-d_H-i-s') . '.json';
                    copy($slidesFile, $backupFile);
                }
                
                file_put_contents($slidesFile, json_encode($slides, JSON_PRETTY_PRINT));
                echo json_encode(['success' => true, 'message' => 'Slides saved successfully']);
                break;
                
            case 'get_footer_messages':
                $messages = file_exists($footerFile) ? json_decode(file_get_contents($footerFile), true) : [];
                if ($messages === null) {
                    throw new Exception('Invalid JSON in footer messages file');
                }
                echo json_encode(['success' => true, 'data' => $messages]);
                break;
                
            case 'save_footer_messages':
                $messages = json_decode($_POST['messages'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Invalid JSON format for footer messages: ' . json_last_error_msg());
                }
                
                // Validate messages structure
                if (!is_array($messages)) {
                    throw new Exception('Footer messages must be an array');
                }
                foreach ($messages as $index => $message) {
                    if (!is_string($message) || trim($message) === '') {
                        throw new Exception("Message $index is empty or invalid");
                    }
                }
                
                // Create backup before saving
                if (file_exists($footerFile)) {
                    $backupFile = $backupDir . '/footer-messages_' . date('Y-m-d_H-i-s') . '.json';
                    copy($footerFile, $backupFile);
                }
                
                file_put_contents($footerFile, json_encode($messages, JSON_PRETTY_PRINT));
                echo json_encode(['success' => true, 'message' => 'Footer messages saved successfully']);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pulse Admin - <?php echo $config['app']['name']; ?></title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
            color: #333;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }
        .user-info {
            position: absolute;
            top: 20px;
            right: 30px;
            text-align: right;
            font-size: 0.9em;
        }
        .user-info a {
            color: white;
            text-decoration: none;
            margin-left: 15px;
            opacity: 0.9;
        }
        .user-info a:hover {
            opacity: 1;
            text-decoration: underline;
        }
        .header h1 {
            margin: 0;
            font-size: 2.5em;
            font-weight: 300;
        }
        .header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
            font-size: 1.1em;
        }
        .content {
            padding: 40px;
        }
        .tabs {
            display: flex;
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 30px;
        }
        .tab {
            padding: 15px 30px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        .tab:hover {
            color: #667eea;
            background: #f8f9fa;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            box-sizing: border-box;
        }
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.2s;
        }
        .btn:hover {
            background: #5a6fd8;
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .btn-success {
            background: #28a745;
        }
        .btn-success:hover {
            background: #218838;
        }
        .slide-item, .message-item {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            background: #f8f9fa;
        }
        .slide-header, .message-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 15px;
        }
        .slide-title {
            font-weight: 600;
            color: #333;
            flex: 1;
        }
        .slide-actions, .message-actions {
            display: flex;
            gap: 10px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .json-editor {
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 13px;
            line-height: 1.4;
            min-height: 300px;
        }
        .preview-link {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #667eea;
            color: white;
            padding: 15px 20px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 500;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            transition: all 0.3s;
        }
        .preview-link:hover {
            background: #5a6fd8;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
        .slide-counter {
            background: #667eea;
            color: white;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
            font-size: 14px;
        }
        .message-counter {
            background: #28a745;
            color: white;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
            font-size: 14px;
        }
        .stats-bar {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .stat-item {
            text-align: center;
        }
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }
        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="user-info">
                Welcome, <?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?>
                <a href="<?php echo UrlHelper::url('index.php'); ?>">🏠 Dashboard</a>
                <a href="<?php echo UrlHelper::url('logout.php'); ?>">🚪 Logout</a>
            </div>
            <h1>📺 Pulse Admin</h1>
            <p>Manage slides and footer messages for the Pulse display system</p>
        </div>
        
        <div class="content">
            <div id="alerts"></div>
            
            <div class="tabs">
                <button class="tab active" onclick="switchTab('slides')">📊 Slides Management</button>
                <button class="tab" onclick="switchTab('footer')">💬 Footer Messages</button>
                <button class="tab" onclick="switchTab('json')">🔧 JSON Editor</button>
            </div>
            
            <!-- Slides Management Tab -->
            <div id="slides-tab" class="tab-content active">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                    <h2>Slides Management</h2>
                    <button class="btn" onclick="addSlide()">➕ Add New Slide</button>
                </div>
                
                <div class="stats-bar">
                    <div class="stat-item">
                        <div class="stat-number" id="total-slides">0</div>
                        <div class="stat-label">Total Slides</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number" id="total-duration">0</div>
                        <div class="stat-label">Total Duration (sec)</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number" id="content-slides">0</div>
                        <div class="stat-label">Content Slides</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number" id="media-slides">0</div>
                        <div class="stat-label">Media Slides</div>
                    </div>
                </div>
                
                <div id="slides-container">
                    <!-- Slides will be loaded here -->
                </div>
                
                <div style="text-align: center; margin-top: 30px;">
                    <button class="btn btn-success" onclick="saveSlides()">💾 Save All Slides</button>
                </div>
            </div>
            
            <!-- Footer Messages Tab -->
            <div id="footer-tab" class="tab-content">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                    <h2>Footer Messages</h2>
                    <button class="btn" onclick="addMessage()">➕ Add New Message</button>
                </div>
                
                <div class="stats-bar">
                    <div class="stat-item">
                        <div class="stat-number" id="total-messages">0</div>
                        <div class="stat-label">Total Messages</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number" id="avg-message-length">0</div>
                        <div class="stat-label">Avg Length</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number" id="rotation-time">0</div>
                        <div class="stat-label">Full Rotation (min)</div>
                    </div>
                </div>
                
                <div id="messages-container">
                    <!-- Messages will be loaded here -->
                </div>
                
                <div style="text-align: center; margin-top: 30px;">
                    <button class="btn btn-success" onclick="saveMessages()">💾 Save All Messages</button>
                </div>
            </div>
            
            <!-- JSON Editor Tab -->
            <div id="json-tab" class="tab-content">
                <h2>Advanced JSON Editor</h2>
                <p>Edit the raw JSON files directly. Be careful with syntax!</p>
                
                <div class="form-row" style="margin-bottom: 20px;">
                    <div>
                        <h3>Slides JSON</h3>
                        <textarea id="slides-json" class="json-editor" placeholder="Loading slides..."></textarea>
                        <button class="btn" onclick="saveJsonSlides()" style="margin-top: 10px;">💾 Save Slides JSON</button>
                    </div>
                    <div>
                        <h3>Footer Messages JSON</h3>
                        <textarea id="footer-json" class="json-editor" placeholder="Loading messages..."></textarea>
                        <button class="btn" onclick="saveJsonMessages()" style="margin-top: 10px;">💾 Save Messages JSON</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <a href="<?php echo UrlHelper::url('pulse/index.html'); ?>" target="_blank" class="preview-link">
        👁️ Preview Pulse
    </a>

    <script>
        let slides = [];
        let messages = [];
        
        // Initialize the admin interface
        document.addEventListener('DOMContentLoaded', function() {
            loadSlides();
            loadMessages();
        });
        
        // Tab switching
        function switchTab(tabName) {
            // Update tab buttons
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');
            
            // Update tab content
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Load JSON editor content if switching to JSON tab
            if (tabName === 'json') {
                loadJsonEditor();
            }
        }
        
        // Show alert messages
        function showAlert(message, type = 'success') {
            const alertsContainer = document.getElementById('alerts');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.textContent = message;
            alertsContainer.appendChild(alert);
            
            setTimeout(() => {
                alert.remove();
            }, 5000);
        }
        
        // AJAX helper
        function ajax(action, data = {}) {
            return fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: action,
                    ...data
                })
            }).then(response => response.json());
        }
        
        // Load slides from server
        function loadSlides() {
            ajax('get_slides').then(response => {
                if (response.success) {
                    slides = response.data;
                    renderSlides();
                } else {
                    showAlert('Failed to load slides: ' + response.error, 'error');
                }
            });
        }
        
        // Load messages from server
        function loadMessages() {
            ajax('get_footer_messages').then(response => {
                if (response.success) {
                    messages = response.data;
                    renderMessages();
                } else {
                    showAlert('Failed to load messages: ' + response.error, 'error');
                }
            });
        }
        
        // Render slides in the UI
        function renderSlides() {
            const container = document.getElementById('slides-container');
            container.innerHTML = '';
            
            // Update statistics
            updateSlidesStats();
            
            slides.forEach((slide, index) => {
                const slideElement = document.createElement('div');
                slideElement.className = 'slide-item';
                slideElement.innerHTML = `
                    <div class="slide-header">
                        <div class="slide-title">
                            <span class="slide-counter">${index + 1}</span>
                            ${slide.type === 'content' ? (slide.content?.title || 'Untitled Slide') : 
                              slide.type === 'iframe' ? (slide.title || 'Iframe Slide') :
                              slide.type === 'video' ? (slide.title || 'Video Slide') :
                              slide.type === 'image' ? (slide.title || 'Image Slide') : 'Unknown Slide'}
                            <small style="color: #666; margin-left: 10px;">(${slide.type})</small>
                        </div>
                        <div class="slide-actions">
                            ${index > 0 ? `<button class="btn btn-secondary" onclick="moveSlide(${index}, -1)">⬆️</button>` : ''}
                            ${index < slides.length - 1 ? `<button class="btn btn-secondary" onclick="moveSlide(${index}, 1)">⬇️</button>` : ''}
                            <button class="btn btn-secondary" onclick="editSlide(${index})">✏️ Edit</button>
                            <button class="btn btn-danger" onclick="deleteSlide(${index})">🗑️ Delete</button>
                        </div>
                    </div>
                    <div id="slide-form-${index}" style="display: none;">
                        ${renderSlideForm(slide, index)}
                    </div>
                    <div id="slide-preview-${index}">
                        ${renderSlidePreview(slide)}
                    </div>
                `;
                container.appendChild(slideElement);
            });
        }
        
        // Render slide form
        function renderSlideForm(slide, index) {
            const isContent = slide.type === 'content';
            const isIframe = slide.type === 'iframe';
            const isVideo = slide.type === 'video';
            const isImage = slide.type === 'image';
            
            return `
                <div class="form-row">
                    <div class="form-group">
                        <label>Slide Type</label>
                        <select onchange="updateSlideType(${index}, this.value)">
                            <option value="content" ${isContent ? 'selected' : ''}>Content</option>
                            <option value="iframe" ${isIframe ? 'selected' : ''}>Iframe</option>
                            <option value="video" ${isVideo ? 'selected' : ''}>Video</option>
                            <option value="image" ${isImage ? 'selected' : ''}>Image</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Duration (seconds)</label>
                        <input type="number" value="${slide.duration || 10}" onchange="updateSlide(${index}, 'duration', this.value)">
                    </div>
                </div>
                <div class="form-group">
                    <label>Slide ID</label>
                    <input type="text" value="${slide.id || ''}" onchange="updateSlide(${index}, 'id', this.value)">
                </div>
                
                ${isContent ? `
                    <div class="form-group">
                        <label>Title</label>
                        <input type="text" value="${slide.content?.title || ''}" onchange="updateSlideContent(${index}, 'title', this.value)">
                    </div>
                    <div class="form-group">
                        <label>Subtitle</label>
                        <input type="text" value="${slide.content?.subtitle || ''}" onchange="updateSlideContent(${index}, 'subtitle', this.value)">
                    </div>
                    <div class="form-group">
                        <label>Body Text</label>
                        <textarea onchange="updateSlideContent(${index}, 'body', this.value)">${slide.content?.body || ''}</textarea>
                    </div>
                    <div class="form-group">
                        <label>List Items (one per line)</label>
                        <textarea onchange="updateSlideContentList(${index}, this.value)">${slide.content?.list ? slide.content.list.join('\\n') : ''}</textarea>
                    </div>
                ` : ''}
                
                ${(isIframe || isVideo || isImage) ? `
                    <div class="form-group">
                        <label>URL</label>
                        <input type="text" value="${slide.url || ''}" onchange="updateSlide(${index}, 'url', this.value)">
                    </div>
                    <div class="form-group">
                        <label>Title</label>
                        <input type="text" value="${slide.title || ''}" onchange="updateSlide(${index}, 'title', this.value)">
                    </div>
                ` : ''}
                
                ${isImage ? `
                    <div class="form-group">
                        <label>Alt Text</label>
                        <input type="text" value="${slide.alt || ''}" onchange="updateSlide(${index}, 'alt', this.value)">
                    </div>
                ` : ''}
                
                <div style="margin-top: 20px;">
                    <button class="btn btn-success" onclick="saveSlideForm(${index})">💾 Save Changes</button>
                    <button class="btn btn-secondary" onclick="cancelSlideEdit(${index})">❌ Cancel</button>
                </div>
            `;
        }
        
        // Render slide preview
        function renderSlidePreview(slide) {
            if (slide.type === 'content') {
                const content = slide.content || {};
                return `
                    <strong>Content Slide:</strong><br>
                    <strong>Title:</strong> ${content.title || 'None'}<br>
                    <strong>Subtitle:</strong> ${content.subtitle || 'None'}<br>
                    <strong>Body:</strong> ${content.body || 'None'}<br>
                    <strong>List Items:</strong> ${content.list ? content.list.length + ' items' : 'None'}<br>
                    <strong>Duration:</strong> ${slide.duration || 10} seconds
                `;
            } else if (slide.type === 'iframe') {
                return `
                    <strong>Iframe Slide:</strong><br>
                    <strong>URL:</strong> ${slide.url || 'Not set'}<br>
                    <strong>Title:</strong> ${slide.title || 'Not set'}<br>
                    <strong>Duration:</strong> ${slide.duration || 10} seconds
                `;
            } else if (slide.type === 'video') {
                return `
                    <strong>Video Slide:</strong><br>
                    <strong>URL:</strong> ${slide.url || 'Not set'}<br>
                    <strong>Title:</strong> ${slide.title || 'Not set'}<br>
                    <strong>Duration:</strong> ${slide.duration || 10} seconds
                `;
            } else if (slide.type === 'image') {
                return `
                    <strong>Image Slide:</strong><br>
                    <strong>URL:</strong> ${slide.url || 'Not set'}<br>
                    <strong>Title:</strong> ${slide.title || 'Not set'}<br>
                    <strong>Alt Text:</strong> ${slide.alt || 'Not set'}<br>
                    <strong>Duration:</strong> ${slide.duration || 10} seconds
                `;
            }
            return 'Unknown slide type';
        }
        
        // Render messages in the UI
        function renderMessages() {
            const container = document.getElementById('messages-container');
            container.innerHTML = '';
            
            // Update statistics
            updateMessagesStats();
            
            messages.forEach((message, index) => {
                const messageElement = document.createElement('div');
                messageElement.className = 'message-item';
                messageElement.innerHTML = `
                    <div class="message-header">
                        <div class="slide-title">
                            <span class="message-counter">${index + 1}</span>
                            ${message.substring(0, 50)}${message.length > 50 ? '...' : ''}
                        </div>
                        <div class="message-actions">
                            ${index > 0 ? `<button class="btn btn-secondary" onclick="moveMessage(${index}, -1)">⬆️</button>` : ''}
                            ${index < messages.length - 1 ? `<button class="btn btn-secondary" onclick="moveMessage(${index}, 1)">⬇️</button>` : ''}
                            <button class="btn btn-secondary" onclick="editMessage(${index})">✏️ Edit</button>
                            <button class="btn btn-danger" onclick="deleteMessage(${index})">🗑️ Delete</button>
                        </div>
                    </div>
                    <div id="message-form-${index}" style="display: none;">
                        <div class="form-group">
                            <label>Message Text</label>
                            <textarea onchange="updateMessage(${index}, this.value)">${message}</textarea>
                        </div>
                        <div style="margin-top: 15px;">
                            <button class="btn btn-success" onclick="saveMessageForm(${index})">💾 Save</button>
                            <button class="btn btn-secondary" onclick="cancelMessageEdit(${index})">❌ Cancel</button>
                        </div>
                    </div>
                    <div id="message-preview-${index}">
                        <strong>Message:</strong> ${message}
                    </div>
                `;
                container.appendChild(messageElement);
            });
        }
        
        // Slide management functions
        function addSlide() {
            const newSlide = {
                type: 'content',
                id: 'slide_' + Date.now(),
                duration: 10,
                content: {
                    title: 'New Slide',
                    subtitle: '',
                    body: ''
                }
            };
            slides.push(newSlide);
            renderSlides();
            editSlide(slides.length - 1);
        }
        
        function editSlide(index) {
            document.getElementById(`slide-form-${index}`).style.display = 'block';
            document.getElementById(`slide-preview-${index}`).style.display = 'none';
        }
        
        function deleteSlide(index) {
            if (confirm('Are you sure you want to delete this slide?')) {
                slides.splice(index, 1);
                renderSlides();
            }
        }
        
        function moveSlide(index, direction) {
            const newIndex = index + direction;
            if (newIndex >= 0 && newIndex < slides.length) {
                const temp = slides[index];
                slides[index] = slides[newIndex];
                slides[newIndex] = temp;
                renderSlides();
            }
        }
        
        function updateSlide(index, field, value) {
            slides[index][field] = value;
        }
        
        function updateSlideContent(index, field, value) {
            if (!slides[index].content) slides[index].content = {};
            slides[index].content[field] = value;
        }
        
        function updateSlideContentList(index, value) {
            if (!slides[index].content) slides[index].content = {};
            slides[index].content.list = value.split('\n').filter(item => item.trim());
        }
        
        function updateSlideType(index, type) {
            const slide = slides[index];
            slide.type = type;
            
            // Reset type-specific fields
            if (type === 'content') {
                slide.content = slide.content || { title: '', subtitle: '', body: '' };
                delete slide.url;
                delete slide.title;
                delete slide.alt;
            } else {
                delete slide.content;
                slide.url = slide.url || '';
                slide.title = slide.title || '';
                if (type === 'image') {
                    slide.alt = slide.alt || '';
                }
            }
            
            renderSlides();
            editSlide(index);
        }
        
        function saveSlideForm(index) {
            document.getElementById(`slide-form-${index}`).style.display = 'none';
            document.getElementById(`slide-preview-${index}`).style.display = 'block';
            renderSlides();
        }
        
        function cancelSlideEdit(index) {
            document.getElementById(`slide-form-${index}`).style.display = 'none';
            document.getElementById(`slide-preview-${index}`).style.display = 'block';
        }
        
        function saveSlides() {
            document.body.classList.add('loading');
            ajax('save_slides', { slides: JSON.stringify(slides) })
                .then(response => {
                    document.body.classList.remove('loading');
                    if (response.success) {
                        showAlert('Slides saved successfully!');
                    } else {
                        showAlert('Failed to save slides: ' + response.error, 'error');
                    }
                });
        }
        
        // Message management functions
        function addMessage() {
            messages.push('New footer message');
            renderMessages();
            editMessage(messages.length - 1);
        }
        
        function editMessage(index) {
            document.getElementById(`message-form-${index}`).style.display = 'block';
            document.getElementById(`message-preview-${index}`).style.display = 'none';
        }
        
        function deleteMessage(index) {
            if (confirm('Are you sure you want to delete this message?')) {
                messages.splice(index, 1);
                renderMessages();
            }
        }
        
        function moveMessage(index, direction) {
            const newIndex = index + direction;
            if (newIndex >= 0 && newIndex < messages.length) {
                const temp = messages[index];
                messages[index] = messages[newIndex];
                messages[newIndex] = temp;
                renderMessages();
            }
        }
        
        function updateMessage(index, value) {
            messages[index] = value;
        }
        
        function saveMessageForm(index) {
            document.getElementById(`message-form-${index}`).style.display = 'none';
            document.getElementById(`message-preview-${index}`).style.display = 'block';
            renderMessages();
        }
        
        function cancelMessageEdit(index) {
            document.getElementById(`message-form-${index}`).style.display = 'none';
            document.getElementById(`message-preview-${index}`).style.display = 'block';
        }
        
        function saveMessages() {
            document.body.classList.add('loading');
            ajax('save_footer_messages', { messages: JSON.stringify(messages) })
                .then(response => {
                    document.body.classList.remove('loading');
                    if (response.success) {
                        showAlert('Footer messages saved successfully!');
                    } else {
                        showAlert('Failed to save messages: ' + response.error, 'error');
                    }
                });
        }
        
        // JSON Editor functions
        function loadJsonEditor() {
            ajax('get_slides').then(response => {
                if (response.success) {
                    document.getElementById('slides-json').value = JSON.stringify(response.data, null, 2);
                }
            });
            
            ajax('get_footer_messages').then(response => {
                if (response.success) {
                    document.getElementById('footer-json').value = JSON.stringify(response.data, null, 2);
                }
            });
        }
        
        function saveJsonSlides() {
            const jsonText = document.getElementById('slides-json').value;
            try {
                JSON.parse(jsonText); // Validate JSON
                ajax('save_slides', { slides: jsonText })
                    .then(response => {
                        if (response.success) {
                            showAlert('Slides JSON saved successfully!');
                            loadSlides(); // Reload the slides
                        } else {
                            showAlert('Failed to save slides JSON: ' + response.error, 'error');
                        }
                    });
            } catch (e) {
                showAlert('Invalid JSON format: ' + e.message, 'error');
            }
        }
        
        function saveJsonMessages() {
            const jsonText = document.getElementById('footer-json').value;
            try {
                JSON.parse(jsonText); // Validate JSON
                ajax('save_footer_messages', { messages: jsonText })
                    .then(response => {
                        if (response.success) {
                            showAlert('Footer messages JSON saved successfully!');
                            loadMessages(); // Reload the messages
                        } else {
                            showAlert('Failed to save messages JSON: ' + response.error, 'error');
                        }
                    });
            } catch (e) {
                showAlert('Invalid JSON format: ' + e.message, 'error');
            }
        }
        
        // Statistics calculation functions
        function updateSlidesStats() {
            const totalSlides = slides.length;
            const totalDuration = slides.reduce((sum, slide) => sum + (parseInt(slide.duration) || 0), 0);
            const contentSlides = slides.filter(slide => slide.type === 'content').length;
            const mediaSlides = slides.filter(slide => ['iframe', 'video', 'image'].includes(slide.type)).length;
            
            document.getElementById('total-slides').textContent = totalSlides;
            document.getElementById('total-duration').textContent = totalDuration;
            document.getElementById('content-slides').textContent = contentSlides;
            document.getElementById('media-slides').textContent = mediaSlides;
        }
        
        function updateMessagesStats() {
            const totalMessages = messages.length;
            const avgLength = totalMessages > 0 ? Math.round(messages.reduce((sum, msg) => sum + msg.length, 0) / totalMessages) : 0;
            const rotationTime = Math.round((totalMessages * 30) / 60); // 30 seconds per message, converted to minutes
            
            document.getElementById('total-messages').textContent = totalMessages;
            document.getElementById('avg-message-length').textContent = avgLength;
            document.getElementById('rotation-time').textContent = rotationTime;
        }
    </script>
</body>
</html>