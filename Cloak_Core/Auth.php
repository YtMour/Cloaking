<?php
namespace Cloak\Core;

/**
 * 认证模块
 * 负责管理后台的用户认证和会话管理
 */
class Auth {
    private $config;
    private $logger;
    
    public function __construct() {
        $this->config = Config::getInstance();
        $this->logger = new Logger();
        
        // 启动会话
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * 验证登录
     */
    public function login($password) {
        $correctPassword = $this->config->get('password', Config::DEFAULT_PASSWORD);
        
        if ($password === $correctPassword) {
            $_SESSION[$this->config->get('session_name')] = true;
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();
            
            $this->logger->logInfo('管理员登录成功', [
                'ip' => $this->getClientIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            
            return true;
        } else {
            $this->logger->logWarning('管理员登录失败', [
                'ip' => $this->getClientIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'password_attempt' => substr($password, 0, 3) . '***'
            ]);
            
            return false;
        }
    }
    
    /**
     * 检查是否已登录
     */
    public function isLoggedIn() {
        $sessionName = $this->config->get('session_name');
        
        if (!isset($_SESSION[$sessionName]) || $_SESSION[$sessionName] !== true) {
            return false;
        }
        
        // 检查会话超时（2小时）
        $timeout = 7200; // 2小时
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
            $this->logout();
            return false;
        }
        
        // 更新最后活动时间
        $_SESSION['last_activity'] = time();
        
        return true;
    }
    
    /**
     * 登出
     */
    public function logout() {
        $sessionName = $this->config->get('session_name');
        
        if (isset($_SESSION[$sessionName])) {
            $this->logger->logInfo('管理员登出', [
                'ip' => $this->getClientIP(),
                'session_duration' => isset($_SESSION['login_time']) ? (time() - $_SESSION['login_time']) : 0
            ]);
        }
        
        // 清除会话数据
        unset($_SESSION[$sessionName]);
        unset($_SESSION['login_time']);
        unset($_SESSION['last_activity']);
        
        // 如果会话为空，销毁会话
        if (empty($_SESSION)) {
            session_destroy();
        }
    }
    
    /**
     * 要求登录
     */
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            $this->showLoginForm();
            exit;
        }
    }
    
    /**
     * 显示登录表单
     */
    public function showLoginForm($error = '') {
        $title = 'Cloak 管理后台';
        $errorMsg = $error ? "<div class='error'>$error</div>" : '';
        
        echo <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>$title</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-header h1 {
            color: #333;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        .login-header p {
            color: #666;
            font-size: 0.9rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e1e5e9;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        .login-btn {
            width: 100%;
            padding: 0.75rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .login-btn:hover {
            transform: translateY(-1px);
        }
        .error {
            background: #fee;
            color: #c33;
            padding: 0.75rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border: 1px solid #fcc;
        }
        .footer {
            text-align: center;
            margin-top: 2rem;
            color: #666;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🛡️ Cloak</h1>
            <p>智能流量过滤系统</p>
        </div>
        
        $errorMsg
        
        <form method="post">
            <div class="form-group">
                <label for="password">管理密码</label>
                <input type="password" id="password" name="password" required autofocus>
            </div>
            
            <button type="submit" class="login-btn">登录管理后台</button>
        </form>
        
        <div class="footer">
            <p>© 2025 Cloak System</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
    
    /**
     * 处理登录请求
     */
    public function handleLoginRequest() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
            $password = $_POST['password'];
            
            if ($this->login($password)) {
                // 登录成功，重定向到管理页面
                $redirect = $_GET['redirect'] ?? $_SERVER['PHP_SELF'];
                header("Location: $redirect");
                exit;
            } else {
                // 登录失败，显示错误信息
                $this->showLoginForm('密码错误，请重试');
                exit;
            }
        }
    }
    
    /**
     * 获取客户端IP
     */
    private function getClientIP() {
        $ipDetector = new IPDetector();
        return $ipDetector->getRealIP();
    }
    
    /**
     * 生成CSRF令牌
     */
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * 验证CSRF令牌
     */
    public function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * 获取会话信息
     */
    public function getSessionInfo() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return [
            'login_time' => $_SESSION['login_time'] ?? 0,
            'last_activity' => $_SESSION['last_activity'] ?? 0,
            'session_duration' => time() - ($_SESSION['login_time'] ?? time()),
            'ip' => $this->getClientIP()
        ];
    }
    
    /**
     * 更改密码
     */
    public function changePassword($oldPassword, $newPassword) {
        $currentPassword = $this->config->get('password', Config::DEFAULT_PASSWORD);
        
        if ($oldPassword !== $currentPassword) {
            return false;
        }
        
        // 这里应该将新密码保存到配置文件
        // 目前只是更新内存中的配置
        $this->config->set('password', $newPassword);
        
        $this->logger->logInfo('管理员密码已更改', [
            'ip' => $this->getClientIP()
        ]);
        
        return true;
    }
    
    /**
     * 检查密码强度
     */
    public function checkPasswordStrength($password) {
        $score = 0;
        $feedback = [];
        
        if (strlen($password) >= 8) {
            $score += 1;
        } else {
            $feedback[] = '密码长度至少8位';
        }
        
        if (preg_match('/[A-Z]/', $password)) {
            $score += 1;
        } else {
            $feedback[] = '包含大写字母';
        }
        
        if (preg_match('/[a-z]/', $password)) {
            $score += 1;
        } else {
            $feedback[] = '包含小写字母';
        }
        
        if (preg_match('/[0-9]/', $password)) {
            $score += 1;
        } else {
            $feedback[] = '包含数字';
        }
        
        if (preg_match('/[^A-Za-z0-9]/', $password)) {
            $score += 1;
        } else {
            $feedback[] = '包含特殊字符';
        }
        
        $strength = ['很弱', '弱', '一般', '强', '很强'][$score] ?? '很弱';
        
        return [
            'score' => $score,
            'strength' => $strength,
            'feedback' => $feedback
        ];
    }
}
?>
