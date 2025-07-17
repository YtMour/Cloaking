<?php
/**
 * Cloak 自动加载器
 * 实现PSR-4自动加载标准
 */

class CloakAutoloader {
    private static $prefixes = [];
    
    /**
     * 注册自动加载器
     */
    public static function register() {
        spl_autoload_register([__CLASS__, 'loadClass']);
        
        // 注册命名空间前缀
        self::addNamespace('Cloak\\Core\\', __DIR__ . '/');
        self::addNamespace('Cloak\\Admin\\', dirname(__DIR__) . '/Cloak_Admin/');
        self::addNamespace('Cloak\\Tools\\', dirname(__DIR__) . '/Cloak_Tools/');
        self::addNamespace('Cloak\\Monitor\\', dirname(__DIR__) . '/Cloak_Monitor/');
        self::addNamespace('Cloak\\API\\', dirname(__DIR__) . '/Cloak_API/');
        self::addNamespace('Cloak\\Backup\\', dirname(__DIR__) . '/Cloak_Backup/');
    }
    
    /**
     * 添加命名空间前缀
     */
    public static function addNamespace($prefix, $base_dir) {
        $prefix = trim($prefix, '\\') . '\\';
        $base_dir = rtrim($base_dir, DIRECTORY_SEPARATOR) . '/';
        
        if (!isset(self::$prefixes[$prefix])) {
            self::$prefixes[$prefix] = [];
        }
        
        array_push(self::$prefixes[$prefix], $base_dir);
    }
    
    /**
     * 加载类文件
     */
    public static function loadClass($class) {
        $prefix = $class;
        
        while (false !== $pos = strrpos($prefix, '\\')) {
            $prefix = substr($class, 0, $pos + 1);
            $relative_class = substr($class, $pos + 1);
            
            $mapped_file = self::loadMappedFile($prefix, $relative_class);
            if ($mapped_file) {
                return $mapped_file;
            }
            
            $prefix = rtrim($prefix, '\\');
        }
        
        return false;
    }
    
    /**
     * 加载映射文件
     */
    protected static function loadMappedFile($prefix, $relative_class) {
        if (!isset(self::$prefixes[$prefix])) {
            return false;
        }
        
        foreach (self::$prefixes[$prefix] as $base_dir) {
            $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
            
            if (self::requireFile($file)) {
                return $file;
            }
        }
        
        return false;
    }
    
    /**
     * 引入文件
     */
    protected static function requireFile($file) {
        if (file_exists($file)) {
            require $file;
            return true;
        }
        return false;
    }
}

// 自动注册加载器
CloakAutoloader::register();
?>
