<?php
namespace Cloak\Core;

/**
 * 配置管理类
 * 统一管理所有配置项和数据文件路径
 */
class Config {
    private static $instance = null;
    private $config = [];
    
    // 数据文件路径配置
    const DATA_PATH = 'Cloak_Data/';
    const UA_BLACKLIST = self::DATA_PATH . 'ua_blacklist.txt';
    const IP_BLACKLIST = self::DATA_PATH . 'ip_blacklist.txt';
    const LOG_FILE = self::DATA_PATH . 'log.txt';
    const LANDING_URL = self::DATA_PATH . 'real_landing_url.txt';
    const API_CONFIG = self::DATA_PATH . 'api_config.json';
    
    // 系统配置
    const DEFAULT_PASSWORD = '123456';
    const SESSION_NAME = 'cloak_auth';
    const LOG_MAX_SIZE = 10485760; // 10MB
    const CACHE_DURATION = 3600; // 1小时
    
    private function __construct() {
        $this->loadDefaultConfig();
    }
    
    /**
     * 获取单例实例
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 获取配置值
     */
    public function get($key, $default = null) {
        return $this->config[$key] ?? $default;
    }
    
    /**
     * 设置配置值
     */
    public function set($key, $value) {
        $this->config[$key] = $value;
    }
    
    /**
     * 获取数据文件路径
     */
    public function getDataPath($file = '') {
        return self::DATA_PATH . $file;
    }
    
    /**
     * 获取UA黑名单文件路径
     */
    public function getUABlacklistPath() {
        return self::UA_BLACKLIST;
    }
    
    /**
     * 获取IP黑名单文件路径
     */
    public function getIPBlacklistPath() {
        return self::IP_BLACKLIST;
    }
    
    /**
     * 获取日志文件路径
     */
    public function getLogPath() {
        return self::LOG_FILE;
    }
    
    /**
     * 获取跳转地址文件路径
     */
    public function getLandingURLPath() {
        return self::LANDING_URL;
    }
    
    /**
     * 获取API配置文件路径
     */
    public function getAPIConfigPath() {
        return self::API_CONFIG;
    }
    
    /**
     * 获取跳转地址
     */
    public function getLandingURL() {
        if (file_exists(self::LANDING_URL)) {
            return trim(file_get_contents(self::LANDING_URL));
        }
        return 'https://www.example.com';
    }
    
    /**
     * 设置跳转地址
     */
    public function setLandingURL($url) {
        $this->ensureDataDirectory();
        return file_put_contents(self::LANDING_URL, trim($url)) !== false;
    }
    
    /**
     * 加载API配置
     */
    public function getAPIConfig() {
        $default = [
            'api_url' => 'https://user-agents.net/download',
            'api_params' => 'crawler=true&limit=500&download=txt',
            'auto_update' => false,
            'update_interval' => 86400
        ];
        
        if (file_exists(self::API_CONFIG)) {
            $config = json_decode(file_get_contents(self::API_CONFIG), true);
            if ($config && is_array($config)) {
                return array_merge($default, $config);
            }
        }
        
        return $default;
    }
    
    /**
     * 保存API配置
     */
    public function saveAPIConfig($config) {
        $this->ensureDataDirectory();
        $config['last_updated'] = date('Y-m-d H:i:s');
        return file_put_contents(self::API_CONFIG, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
    }
    
    /**
     * 确保数据目录存在
     */
    public function ensureDataDirectory() {
        if (!is_dir(self::DATA_PATH)) {
            mkdir(self::DATA_PATH, 0755, true);
        }
        
        $subdirs = ['backup', 'logs'];
        foreach ($subdirs as $subdir) {
            $path = self::DATA_PATH . $subdir;
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
    }
    
    /**
     * 检查文件权限
     */
    public function checkPermissions() {
        $this->ensureDataDirectory();
        
        $files = [
            self::UA_BLACKLIST,
            self::IP_BLACKLIST,
            self::LOG_FILE,
            self::LANDING_URL,
            self::API_CONFIG
        ];
        
        $issues = [];
        foreach ($files as $file) {
            if (file_exists($file) && !is_writable($file)) {
                $issues[] = $file . ' 不可写';
            }
        }
        
        if (!is_writable(self::DATA_PATH)) {
            $issues[] = self::DATA_PATH . ' 目录不可写';
        }
        
        return $issues;
    }
    
    /**
     * 加载默认配置
     */
    private function loadDefaultConfig() {
        $this->config = [
            'password' => self::DEFAULT_PASSWORD,
            'session_name' => self::SESSION_NAME,
            'log_max_size' => self::LOG_MAX_SIZE,
            'cache_duration' => self::CACHE_DURATION,
            'timezone' => 'Asia/Shanghai',
            'debug' => false
        ];
    }
    
    /**
     * 从文件加载配置
     */
    public function loadFromFile($file) {
        if (file_exists($file)) {
            $config = include $file;
            if (is_array($config)) {
                $this->config = array_merge($this->config, $config);
            }
        }
    }
}
?>
