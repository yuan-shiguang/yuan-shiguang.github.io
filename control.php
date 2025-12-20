<?php
// Valine 评论管理界面
// 配置文件
define('VALINE_APP_ID', 'HNFmOZe22FZcmjCkqC2B3oPT-gzGzoHsz');
define('VALINE_APP_KEY', '0aODg2m6jolQCfVfkvPgmLhX');
define('VALINE_SERVER_URL', 'https://hnfmoze2.lc-cn-n1-shared.com');
define('VALINE_MASTER_KEY', 'QcRWzeXVvb9FpZgUqXQ14hVg'); 

// 用户凭证
define('ADMIN_USERNAME', 'yuanshiguang');
define('ADMIN_PASSWORD', 'as1234as');

// 启动会话
session_start();

// 检查登录状态
function checkLogin() {
    return isset($_SESSION['valine_admin_logged_in']) && $_SESSION['valine_admin_logged_in'] === true;
}

// 登录处理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'login') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
            $_SESSION['valine_admin_logged_in'] = true;
            $_SESSION['success_message'] = "登录成功";
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $_SESSION['error_message'] = "用户名或密码错误";
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    } elseif ($_POST['action'] === 'logout') {
        session_destroy();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } elseif ($_POST['action'] === 'control' && checkLogin()) {
        $commentId = $_POST['comment_id'] ?? '';
        
        if (!empty($commentId)) {
            // 使用Master Key删除评论
            $url = VALINE_SERVER_URL . '/1.1/classes/Comment/' . $commentId;
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'X-LC-Id: ' . VALINE_APP_ID,
                'X-LC-Key: ' . VALINE_MASTER_KEY . ',master',
                'Content-Type: application/json'
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $_SESSION['success_message'] = "评论删除成功";
            } else {
                // 尝试使用App Key删除（如果ACL允许）
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'X-LC-Id: ' . VALINE_APP_ID,
                    'X-LC-Key: ' . VALINE_APP_KEY,
                    'Content-Type: application/json'
                ]);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode === 200) {
                    $_SESSION['success_message'] = "评论删除成功";
                } else {
                    $_SESSION['error_message'] = "删除失败，HTTP代码: " . $httpCode . "。请检查Master Key配置。";
                }
            }
            
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}

// 获取评论数据
$comments = [];
$stats = [
    'total' => 0,
    'today' => 0,
    'pages' => 0
];

if (checkLogin()) {
    // 使用REST API获取评论
    $url = VALINE_SERVER_URL . '/1.1/classes/Comment?order=-createdAt&limit=1000';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-LC-Id: ' . VALINE_APP_ID,
        'X-LC-Key: ' . VALINE_APP_KEY,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        $comments = $data['results'] ?? [];
        
        // 计算统计信息
        $stats['total'] = count($comments);
        
        $today = date('Y-m-d');
        $uniquePages = [];
        
        foreach ($comments as $comment) {
            $commentDate = date('Y-m-d', strtotime($comment['createdAt']));
            if ($commentDate === $today) {
                $stats['today']++;
            }
            
            $url = $comment['url'] ?? '/';
            $uniquePages[$url] = true;
        }
        
        $stats['pages'] = count($uniquePages);
    } else {
        $_SESSION['error_message'] = "加载评论失败，HTTP代码: " . $httpCode;
    }
}

// 显示成功/错误消息
$successMessage = $_SESSION['success_message'] ?? '';
$errorMessage = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

$isLoggedIn = checkLogin();
?>
<!DOCTYPE html>
<html lang="zh-CN" theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Valine 评论管理界面</title>
    <link href="https://cdn.staticfile.org/font-awesome/6.4.2/css/fontawesome.min.css" rel="stylesheet">
    <link href="https://cdn.staticfile.org/font-awesome/6.4.2/css/brands.min.css" rel="stylesheet">
    <link href="https://cdn.staticfile.org/font-awesome/6.4.2/css/solid.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Roboto', sans-serif;
        }

        :root {
            --bg-color: #1a1a1a;
            --card-bg: #2d2d2d;
            --text-color: #e0e0e0;
            --accent-color: #4dabf7;
            --border-color: #404040;
            --danger-color: #ff6b6b;
            --success-color: #51cf66;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            flex: 1;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 30px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-dots {
            display: flex;
            gap: 4px;
        }

        .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: var(--accent-color);
        }

        h1 {
            font-size: 24px;
            font-weight: 600;
        }

        .login-form {
            background-color: var(--card-bg);
            border-radius: 8px;
            padding: 30px;
            max-width: 400px;
            margin: 50px auto;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .login-form h2 {
            margin-bottom: 20px;
            text-align: center;
            color: var(--accent-color);
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }

        input {
            width: 100%;
            padding: 12px;
            background-color: rgba(255, 255, 255, 0.1);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            color: var(--text-color);
            font-size: 16px;
        }

        input:focus {
            outline: none;
            border-color: var(--accent-color);
        }

        button {
            background-color: var(--accent-color);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            width: 100%;
            transition: background-color 0.2s;
        }

        button:hover {
            background-color: #3a93d4;
        }

        .dashboard {
            <?php if (!$isLoggedIn): ?>display: none;<?php endif; ?>
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background-color: var(--card-bg);
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: var(--accent-color);
        }

        .stat-label {
            font-size: 14px;
            opacity: 0.8;
        }

        .comments-list {
            background-color: var(--card-bg);
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 30px;
        }

        .comment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .comment-item {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            transition: background-color 0.2s;
        }

        .comment-item:hover {
            background-color: rgba(255, 255, 255, 0.05);
        }

        .comment-item:last-child {
            border-bottom: none;
        }

        .comment-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
            opacity: 0.8;
        }

        .comment-author {
            font-weight: 500;
            color: var(--accent-color);
        }

        .comment-content {
            margin-bottom: 15px;
            line-height: 1.5;
            white-space: pre-wrap;
        }

        .comment-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background-color: #ff5252;
        }

        .btn-success {
            background-color: var(--success-color);
            color: white;
        }

        .logout-btn {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-color);
            width: auto;
            padding: 8px 16px;
        }

        .logout-btn:hover {
            background-color: rgba(255, 255, 255, 0.05);
        }

        .alert {
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .alert-error {
            background-color: rgba(255, 107, 107, 0.2);
            border: 1px solid var(--danger-color);
        }

        .alert-success {
            background-color: rgba(81, 207, 102, 0.2);
            border: 1px solid var(--success-color);
        }

        .loading {
            text-align: center;
            padding: 20px;
            opacity: 0.7;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            opacity: 0.7;
        }

        footer {
            text-align: center;
            padding: 20px;
            border-top: 1px solid var(--border-color);
            margin-top: auto;
            font-size: 14px;
            opacity: 0.7;
        }

        .search-container {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        @media (max-width: 768px) {
            .stats {
                grid-template-columns: 1fr;
            }
            
            .comment-meta {
                flex-direction: column;
                gap: 5px;
            }
            
            .comment-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .search-container {
                flex-direction: column;
                width: 100%;
            }
            
            .search-container input {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo">
                <div class="logo-dots">
                    <div class="dot"></div>
                    <div class="dot"></div>
                    <div class="dot"></div>
                    <div class="dot"></div>
                </div>
                <h1>Valine 评论管理界面</h1>
            </div>
            <?php if ($isLoggedIn): ?>
            <form method="post" style="display: inline;">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="logout-btn">
                    <i class="fa-solid fa-right-from-bracket"></i> 退出登录
                </button>
            </form>
            <?php endif; ?>
        </header>

        <?php if (!$isLoggedIn): ?>
        <div class="login-form">
            <h2>管理员登录</h2>
            <?php if (isset($errorMessage)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($errorMessage); ?></div>
            <?php endif; ?>
            <form method="post" autocomplete="off">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label for="username">用户名</label>
                    <input type="text" id="username" name="username" placeholder="请输入用户名" autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="password">密码</label>
                    <input type="password" id="password" name="password" placeholder="请输入密码" autocomplete="new-password">
                </div>
                <button type="submit">登录</button>
            </form>
            <div style="margin-top: 15px; font-size: 14px; opacity: 0.7; text-align: center;">
                默认账号: yuanshiguang / as1234as
            </div>
        </div>
        <?php else: ?>
        <div class="dashboard">
            <?php if ($successMessage): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
            <?php endif; ?>
            <?php if ($errorMessage): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($errorMessage); ?></div>
            <?php endif; ?>
            
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">总评论数</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['today']; ?></div>
                    <div class="stat-label">今日评论</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['pages']; ?></div>
                    <div class="stat-label">有评论的页面</div>
                </div>
            </div>

            <div class="comments-list">
                <div class="comment-header">
                    <h3>评论列表 (<?php echo count($comments); ?> 条)</h3>
                    <div class="search-container">
                        <input type="text" id="searchInput" placeholder="搜索评论..." style="padding: 8px; background-color: rgba(255,255,255,0.1); border: 1px solid var(--border-color); border-radius: 4px; color: var(--text-color;">
                        <button id="refreshBtn" style="width: auto; padding: 8px 16px;">
                            <i class="fa-solid fa-rotate"></i> 刷新
                        </button>
                    </div>
                </div>
                <div id="commentsContainer">
                    <?php if (empty($comments)): ?>
                    <div class="empty-state">暂无评论</div>
                    <?php else: ?>
                    <?php foreach ($comments as $comment): ?>
                    <div class="comment-item" data-comment-id="<?php echo $comment['objectId']; ?>">
                        <div class="comment-meta">
                            <div>
                                <span class="comment-author"><?php echo htmlspecialchars($comment['nick'] ?? '匿名用户'); ?></span>
                                <span>评论于 <?php echo date('Y-m-d H:i:s', strtotime($comment['createdAt'])); ?></span>
                            </div>
                            <div>页面: <?php echo htmlspecialchars($comment['url'] ?? '/'); ?></div>
                        </div>
                        <div class="comment-content"><?php echo htmlspecialchars($comment['comment'] ?? ''); ?></div>
                        <div class="comment-meta">
                            <div>邮箱: <?php echo htmlspecialchars($comment['mail'] ?? '未提供'); ?></div>
                            <div>网站: <?php echo htmlspecialchars($comment['link'] ?? '未提供'); ?></div>
                        </div>
                        <div class="comment-actions">
                            <form method="post" onsubmit="return confirm('确定要删除这条评论吗？此操作不可撤销。');">
                                <input type="hidden" name="action" value="control">
                                <input type="hidden" name="comment_id" value="<?php echo $comment['objectId']; ?>">
                                <button type="submit" class="btn btn-danger">
                                    <i class="fa-solid fa-trash"></i> 删除
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <footer>
        &copy; 2025 元时光<br>
        Powered by Hexo & Theme Vivia
    </footer>

    <script>
        // 搜索功能
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const refreshBtn = document.getElementById('refreshBtn');
            
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const commentItems = document.querySelectorAll('.comment-item');
                    let visibleCount = 0;
                    
                    commentItems.forEach(item => {
                        const text = item.textContent.toLowerCase();
                        if (text.includes(searchTerm)) {
                            item.style.display = 'block';
                            visibleCount++;
                        } else {
                            item.style.display = 'none';
                        }
                    });
                    
                    // 如果没有匹配的评论，显示提示
                    const emptyMsg = document.getElementById('searchEmptyMsg');
                    if (visibleCount === 0 && searchTerm) {
                        if (!emptyMsg) {
                            const msg = document.createElement('div');
                            msg.id = 'searchEmptyMsg';
                            msg.className = 'empty-state';
                            msg.textContent = '没有找到匹配的评论';
                            document.getElementById('commentsContainer').appendChild(msg);
                        }
                    } else if (emptyMsg) {
                        emptyMsg.remove();
                    }
                });
            }
            
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function() {
                    window.location.reload();
                });
            }
            
            // 清除登录表单的自动填充
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');
            
            if (usernameInput) {
                usernameInput.value = '';
            }
            if (passwordInput) {
                passwordInput.value = '';
            }
        });
    </script>
</body>
</html>
