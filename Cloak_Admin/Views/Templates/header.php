<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8" />
    <title><?php echo $title ?? 'Cloak 管理后台'; ?></title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }

        .main-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            position: relative;
            background: rgba(255,255,255,0.95);
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
        }

        .logout-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #dc3545;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220,53,69,0.3);
        }

        .nav-menu {
            display: flex;
            gap: 15px;
            margin-top: 15px;
            flex-wrap: wrap;
        }

        .nav-btn {
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .nav-btn:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }

        .nav-btn.active {
            background: #28a745;
        }

        h2 {
            color: #2c3e50;
            margin: 0 60px 0 0;
            font-size: 28px;
            font-weight: 600;
        }

        .card {
            background: rgba(255,255,255,0.95);
            padding: 25px;
            margin-bottom: 25px;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }

        .msg {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-weight: 500;
            border-left: 4px solid;
        }

        .success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border-left-color: #28a745;
        }

        .error {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border-left-color: #dc3545;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            margin: 2px;
        }

        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-info { background: #17a2b8; color: white; }
        .btn-secondary { background: #6c757d; color: white; }

        .btn:hover { transform: translateY(-1px); opacity: 0.9; }

        .table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-top: 15px;
        }

        .table th, .table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }

        .table tr:hover {
            background: rgba(0, 123, 255, 0.05);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2c3e50;
        }

        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }

        @media (max-width: 768px) {
            body { padding: 10px; }
            .header h2 { font-size: 20px; margin-right: 80px; }
            .nav-menu { gap: 10px; }
            .nav-btn { padding: 8px 16px; font-size: 13px; }
        }
    </style>
</head>
<body>

<div class="main-container">
    <div class="header">
        <h2>斗篷系统后台管理</h2>
        <a href="Cloak_admin.php?action=logout" class="logout-btn">🚪 退出后台</a>
        <div class="nav-menu">
            <a href="Cloak_admin.php" class="nav-btn <?php echo ($module ?? '') === 'dashboard' ? 'active' : ''; ?>">📊 仪表板</a>
            <a href="Cloak_admin.php?module=monitor" class="nav-btn <?php echo ($module ?? '') === 'monitor' ? 'active' : ''; ?>">📋 监控日志</a>
            <a href="Cloak_admin.php?module=ua" class="nav-btn <?php echo ($module ?? '') === 'ua' ? 'active' : ''; ?>">🚫 UA管理</a>
            <a href="Cloak_admin.php?module=ip" class="nav-btn <?php echo ($module ?? '') === 'ip' ? 'active' : ''; ?>">🌐 IP管理</a>
            <a href="Cloak_admin.php?module=tools" class="nav-btn <?php echo ($module ?? '') === 'tools' ? 'active' : ''; ?>">🔧 工具</a>
        </div>
    </div>

    <?php if (isset($msg) && !empty($msg)): ?>
        <div class="msg <?php echo $msg_type ?? 'success'; ?>">
            <?php echo htmlspecialchars($msg); ?>
        </div>
    <?php endif; ?>

