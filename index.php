
<?php
// اتصال بقاعدة البيانات
$servername = "localhost";
$username = "root";     // عادةً في XAMPP اسم المستخدم هو root
$password = "";         // كلمة المرور غالباً فارغة في XAMPP
$dbname = "cmd_agency"; // اسم قاعدة البيانات التي أنشأتها

// إنشاء الاتصال
$conn = new mysqli($servername, $username, $password, $dbname);

// التحقق من الاتصال
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// إنشاء الجداول إذا لم تكن موجودة
function createTables($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS services (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        category VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        icon VARCHAR(255) NOT NULL
    )";
    
    $conn->query($sql);
    
    $sql = "CREATE TABLE IF NOT EXISTS projects (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        category VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        image TEXT
    )";
    
    $conn->query($sql);
    
    $sql = "CREATE TABLE IF NOT EXISTS categories (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL
    )";
    
    $conn->query($sql);
    
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        password VARCHAR(255) NOT NULL
    )";
    
    $conn->query($sql);
    
    // إضافة مستخدم افتراضي إذا لم يكن موجودًا
    $sql = "SELECT * FROM users WHERE email='admin@cmd.com'";
    $result = $conn->query($sql);
    
    if ($result->num_rows == 0) {
        $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (username, email, password) VALUES ('admin', 'admin@cmd.com', '$hashed_password')";
        $conn->query($sql);
    }
}

// استدعاء الدالة لإنشاء الجداول
createTables($conn);

// جلب البيانات من قاعدة البيانات
function getServices($conn) {
    $services = array();
    $sql = "SELECT * FROM services";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $services[] = $row;
        }
    }
    
    return $services;
}

function getProjects($conn) {
    $projects = array();
    $sql = "SELECT * FROM projects";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $projects[] = $row;
        }
    }
    
    return $projects;
}

function getCategories($conn) {
    $categories = array();
    $sql = "SELECT name FROM categories";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $categories[] = $row['name'];
        }
    }
    
    return $categories;
}

// معالجة طلبات POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    session_start();
    
    // تسجيل الدخول
    if (isset($_POST['login'])) {
        $email = $_POST['email'];
        $password = $_POST['password'];
        
        $sql = "SELECT * FROM users WHERE email='$email'";
        $result = $conn->query($sql);
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['isLoggedIn'] = true;
                $_SESSION['user'] = $user;
                echo json_encode(['success' => true]);
                exit;
            }
        }
        
        echo json_encode(['success' => false, 'message' => 'بيانات الدخول غير صحيحة']);
        exit;
    }
    
    // تسجيل الخروج
    if (isset($_POST['logout'])) {
        session_destroy();
        echo json_encode(['success' => true]);
        exit;
    }
    
    // إضافة/تعديل خدمة
    if (isset($_POST['save_service'])) {
        $id = $_POST['id'];
        $title = $_POST['title'];
        $category = $_POST['category'];
        $description = $_POST['description'];
        $icon = $_POST['icon'];
        
        if ($id) {
            // تحديث الخدمة
            $sql = "UPDATE services SET title='$title', category='$category', description='$description', icon='$icon' WHERE id=$id";
        } else {
            // إضافة خدمة جديدة
            $sql = "INSERT INTO services (title, category, description, icon) VALUES ('$title', '$category', '$description', '$icon')";
        }
        if ($conn->query($sql)) {
    // إضافة التصنيف إذا لم يكن موجودًا
    $check_category = "SELECT * FROM categories WHERE name='$category'";
    $result = $conn->query($check_category);
    
    if ($result->num_rows == 0) {
        $conn->query("INSERT INTO categories (name) VALUES ('$category')");
    }
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $conn->error]);
}
exit;
    }
    
    // حذف خدمة
    if (isset($_POST['delete_service'])) {
        $id = $_POST['id'];
        $sql = "DELETE FROM services WHERE id=$id";
        
        if ($conn->query($sql)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        exit;
    }
    
    // إضافة/تعديل مشروع
    if (isset($_POST['save_project'])) {
        $id = $_POST['id'];
        $title = $_POST['title'];
        $category = $_POST['category'];
        $description = $_POST['description'];
        
        // معالجة صورة المشروع
        $image = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $target_dir = "uploads/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $target_file = $target_dir . basename($_FILES["image"]["name"]);
            $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
            
            // التحقق من أن الملف صورة
            $check = getimagesize($_FILES["image"]["tmp_name"]);
            if ($check !== false) {
                // إنشاء اسم فريد للصورة
                $new_filename = uniqid() . '.' . $imageFileType;
                $target_file = $target_dir . $new_filename;
                
                if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                    $image = $target_file;
                }
            }
        } elseif (isset($_POST['existing_image'])) {
            $image = $_POST['existing_image'];
        }
        
        if ($id) {
            // تحديث المشروع
            if ($image) {
                $sql = "UPDATE projects SET title='$title', category='$category', description='$description', image='$image' WHERE id=$id";
            } else {
                $sql = "UPDATE projects SET title='$title', category='$category', description='$description' WHERE id=$id";
            }
        } else {
            // إضافة مشروع جديد
            $sql = "INSERT INTO projects (title, category, description, image) VALUES ('$title', '$category', '$description', '$image')";
        }
        
        if ($conn->query($sql)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        exit;
    }
    
    // حذف مشروع
    if (isset($_POST['delete_project'])) {
        $id = $_POST['id'];
        
        // حذف الصورة أولاً إذا كانت موجودة
        $sql = "SELECT image FROM projects WHERE id=$id";
        $result = $conn->query($sql);
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if ($row['image'] && file_exists($row['image'])) {
                unlink($row['image']);
            }
        }
        
        $sql = "DELETE FROM projects WHERE id=$id";
        
        if ($conn->query($sql)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        exit;
    }
    
    // تحديث إعدادات المستخدم
    if (isset($_POST['save_settings'])) {
        $username = $_POST['username'];
        $email = $_POST['email'];
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        $user_id = $_SESSION['user']['id'];
        $sql = "UPDATE users SET username='$username', email='$email'";
        
        if ($password && $password == $confirm_password) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql .= ", password='$hashed_password'";
        }
        
        $sql .= " WHERE id=$user_id";
        
        if ($conn->query($sql)) {
            $_SESSION['user']['username'] = $username;
            $_SESSION['user']['email'] = $email;
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        exit;
    }
}

// جلب البيانات الحالية
$services = getServices($conn);
$projects = getProjects($conn);
$categories = getCategories($conn);

// التحقق من حالة تسجيل الدخول
session_start();
$isLoggedIn = isset($_SESSION['isLoggedIn']) && $_SESSION['isLoggedIn'];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="CMD - وكالة متخصصة في تصميم المواقع، الشعارات، إدارة الحسابات، وتقديم الحلول الرقمية المتكاملة">
    <meta name="keywords" content="تصميم مواقع, إدارة حسابات, تصميم شعارات, استضافة مواقع, إعلانات, مونتاج">
    <title>CMD | Code Marketing Design</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0072FF;
            --primary-dark: #0044CC;
            --primary-light: #00C6FF;
            --secondary: #FF512F;
            --accent: #F09819;
            --success: #8E2DE2;
            --warning: #F09819;
            --danger: #FF512F;
            
            --bg-primary: #FFFFFF;
            --bg-secondary: #F8F9FA;
            --bg-tertiary: #E9ECEF;
            --text-primary: #000000;
            --text-secondary: #6C757D;
            --text-muted: #ADB5BD;
            
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 8px 25px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 15px 35px rgba(0, 0, 0, 0.12);
            --shadow-xl: 0 25px 50px rgba(0, 0, 0, 0.15);
            
            --border-radius: 12px;
            --border-radius-lg: 20px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-fast: all 0.15s ease;
            
            --gradient-primary: linear-gradient(135deg, var(--primary), var(--primary-light));
            --gradient-secondary: linear-gradient(135deg, var(--secondary), var(--accent));
            --gradient-success: linear-gradient(135deg, #8E2DE2, #4A00E0);
        }

        .dark-mode {
            --bg-primary: #000000;
            --bg-secondary: #1E293B;
            --bg-tertiary: #334155;
            --text-primary: #F1F5F9;
            --text-secondary: #CBD5E1;
            --text-muted: #64748B;
            
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.3);
            --shadow-md: 0 8px 25px rgba(0, 0, 0, 0.4);
            --shadow-lg: 0 15px 35px rgba(0, 0, 0, 0.5);
            --shadow-xl: 0 25px 50px rgba(0, 0, 0, 0.6);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
            scroll-padding-top: 80px;
        }

        body {
            font-family: 'Cairo', Arial, sans-serif;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.7;
            overflow-x: hidden;
            transition: var(--transition);
        }

        /* صفحة تسجيل الدخول */
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            background-color: var(--bg-secondary);
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 3000;
        }

        .login-form {
            width: 100%;
            max-width: 400px;
            background-color: var(--bg-primary);
            padding: 30px;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-xl);
            text-align: center;
            position: relative;
        }

        .login-close {
            position: absolute;
            top: 15px;
            left: 15px;
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 1.5rem;
            cursor: pointer;
        }

        .login-logo h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .login-logo p {
            font-size: 1rem;
            color: var(--text-secondary);
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 1.5rem;
            text-align: right;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .form-control {
            width: 100%;
            padding: 1rem;
            border: 2px solid var(--bg-tertiary);
            border-radius: var(--border-radius);
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 1rem;
            transition: var(--transition);
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 114, 255, 0.1);
        }

        .alert-error {
            background-color: #f8d7da;
            color: #842029;
            padding: 10px;
            border-radius: var(--border-radius);
            margin-top: 15px;
            font-size: 0.9rem;
            text-align: center;
            display: none;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .container-fluid {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Progress Bar */
        .scroll-progress {
            position: fixed;
            top: 0;
            right: 0;
            width: 0%;
            height: 4px;
            background: var(--gradient-primary);
            z-index: 1001;
            transition: width 0.1s ease;
        }

        /* Header */
        .header {
            position: fixed;
            top: 0;
            width: 100%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            z-index: 1000;
            transition: var(--transition);
        }

        .dark-mode .header {
            background: rgba(15, 23, 42, 0.95);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .header.scrolled {
            box-shadow: var(--shadow-md);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
        }

        .logo {
            font-size: 2rem;
            font-weight: 800;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .logo-text {
            font-size: 1.5rem;
        }

        .logo-small {
            font-size: 0.8rem;
            font-weight: 400;
            display: block;
            line-height: 1.2;
        }

        .nav {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 1.5rem;
            align-items: center;
        }

        .nav-link {
            text-decoration: none;
            color: var(--text-primary);
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            transition: var(--transition);
            position: relative;
        }

        .nav-link::before {
            content: '';
            position: absolute;
            bottom: -2px;
            right: 50%;
            width: 0;
            height: 2px;
            background: var(--gradient-primary);
            transition: var(--transition);
            transform: translateX(50%);
        }

        .nav-link:hover::before,
        .nav-link.active::before {
            width: 100%;
        }

        .nav-link:hover {
            color: var(--primary);
        }

        .theme-toggle {
            background: var(--bg-secondary);
            border: none;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }

        .theme-toggle:hover {
            transform: scale(1.1);
            box-shadow: var(--shadow-md);
        }

        .mobile-menu {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-primary);
            cursor: pointer;
        }

        .admin-link {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 0.5rem 1rem;
            background: var(--gradient-primary);
            color: white;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            font-size: 0.9rem;
        }
        
        .admin-link:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* Hero Section */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 120px 0 80px;
            background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-secondary) 100%);
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="%23E5E7EB" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.3;
        }

        .hero-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
            position: relative;
            z-index: 1;
        }

        .hero-text h1 {
            font-size: 3.5rem;
            font-weight: 900;
            line-height: 1.2;
            margin-bottom: 1.5rem;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .hero-text .typing-text {
            font-size: 1.5rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
            min-height: 3rem;
        }

        .hero-text p {
            font-size: 1.1rem;
            color: var(--text-secondary);
            margin-bottom: 2.5rem;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .hero-image {
            display: flex;
            justify-content: center;
            position: relative;
        }

        .agency-image {
            width: 100%;
            max-width: 500px;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-xl);
            position: relative;
            z-index: 2;
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 4rem;
            overflow: hidden;
            aspect-ratio: 1/1;
        }

        .agency-image::before {
            content: 'CMD';
            position: absolute;
            font-size: 5rem;
            font-weight: 900;
            opacity: 0.2;
            transform: rotate(-30deg);
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem 2rem;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            font-family: inherit;
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: white;
            box-shadow: var(--shadow-md);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-outline {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .btn-outline:hover {
            background: var(--primary);
            color: white;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }

        /* Sections */
        .section {
            padding: 100px 0;
            position: relative;
        }

        .section-title {
            text-align: center;
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .section-subtitle {
            text-align: center;
            font-size: 1.1rem;
            color: var(--text-secondary);
            margin-bottom: 4rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        /* About Section */
        .about-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }

        .about-text {
            font-size: 1.1rem;
            line-height: 1.8;
        }

        .about-text p {
            margin-bottom: 1.5rem;
            color: var(--text-secondary);
        }

        .about-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 2rem;
            margin-top: 2rem;
        }

        .stat-item {
            text-align: center;
            padding: 1.5rem;
            background: var(--bg-secondary);
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .stat-item:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary);
        }

        .stat-label {
            color: var(--text-secondary);
            margin-top: 0.5rem;
        }

        .about-info {
            background: var(--bg-secondary);
            padding: 2.5rem;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-sm);
        }

        .info-grid {
            display: grid;
            gap: 1.5rem;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .info-icon {
            width: 45px;
            height: 45px;
            background: var(--gradient-primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        /* Services Section */
        .services-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .service-card {
            background: var(--bg-secondary);
            padding: 2.5rem 2rem;
            border-radius: var(--border-radius-lg);
            text-align: center;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .service-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
            transform: scaleX(0);
            transition: var(--transition);
        }

        .service-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-lg);
        }

        .service-card:hover::before {
            transform: scaleX(1);
        }

        .service-icon {
            width: 80px;
            height: 80px;
            background: var(--gradient-primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 1.5rem;
            transition: var(--transition);
        }

        .service-card:hover .service-icon {
            transform: scale(1.1);
        }

        .service-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .service-description {
            color: var(--text-secondary);
            line-height: 1.6;
        }

        /* Portfolio Section */
        .portfolio-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
        }

        .portfolio-item {
            background: var(--bg-secondary);
            border-radius: var(--border-radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            position: relative;
        }

        .portfolio-item:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-xl);
        }

        .portfolio-image {
            width: 100%;
            height: 250px;
            background: var(--gradient-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .portfolio-image img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 1;
        }

        .portfolio-image i {
            position: relative;
            z-index: 2;
        }

        .portfolio-image::before {
            content: 'CMD';
            position: absolute;
            font-size: 5rem;
            font-weight: 900;
            opacity: 0.2;
            transform: rotate(-30deg);
            z-index: 2;
        }

        .portfolio-content {
            padding: 2rem;
        }

        .portfolio-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .portfolio-category {
            background: var(--success);
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-block;
            margin-bottom: 1rem;
        }

        .portfolio-description {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        /* Social Media Section */
        .social-media {
            background: var(--bg-secondary);
            padding: 100px 0;
        }

        .social-icons {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 2rem;
            margin-top: 3rem;
        }

        .social-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            transition: var(--transition);
            box-shadow: var(--shadow-md);
        }

        .social-icon:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-xl);
        }

        .facebook { background: #3b5998; }
        .twitter { background: #1da1f2; }
        .instagram { background: linear-gradient(45deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888); }
        .tiktok { background: #000000; }
        .youtube { background: #ff0000; }
        .linkedin { background: #0077b5; }
        .snapchat { background: #fffc00; color: #000; }
        .whatsapp { background: #25D366; }
        .telegram { background: #0088cc; }

        /* Process Section */
        .process-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }

        .process-step {
            text-align: center;
            padding: 2rem;
            background: var(--bg-secondary);
            border-radius: var(--border-radius-lg);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .step-number {
            width: 50px;
            height: 50px;
            background: var(--gradient-primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0 auto 1.5rem;
            position: relative;
            z-index: 2;
        }

        .process-step::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
            transform: scaleX(0);
            transition: var(--transition);
        }

        .process-step:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .process-step:hover::before {
            transform: scaleX(1);
        }

        .step-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .step-description {
            color: var(--text-secondary);
            line-height: 1.6;
        }

        /* Contact Section */
        .contact-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
        }

        .contact-info {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.5rem;
            background: var(--bg-secondary);
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .contact-item:hover {
            transform: translateX(-5px);
            box-shadow: var(--shadow-md);
        }

        .contact-icon {
            width: 50px;
            height: 50px;
            background: var(--gradient-primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }

        .contact-form {
            background: var(--bg-secondary);
            padding: 2.5rem;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-sm);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .form-control {
            width: 100%;
            padding: 1rem;
            border: 2px solid var(--bg-tertiary);
            border-radius: var(--border-radius);
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 1rem;
            transition: var(--transition);
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 114, 255, 0.1);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }

        /* Footer */
        .footer {
            background: var(--bg-secondary);
            padding: 3rem 0 2rem;
            text-align: center;
            border-top: 1px solid var(--bg-tertiary);
        }

        .social-links {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .social-link {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: var(--transition);
            text-decoration: none;
        }

        .social-link:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-5px);
        }

        /* Scroll to top */
        .scroll-top {
            position: fixed;
            bottom: 30px;
            left: 30px;
            width: 50px;
            height: 50px;
            background: var(--gradient-primary);
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 1.2rem;
            cursor: pointer;
            opacity: 0;
            transform: translateY(100px);
            transition: var(--transition);
            z-index: 999;
            box-shadow: var(--shadow-lg);
        }

        .scroll-top.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .scroll-top:hover {
            transform: translateY(-5px);
        }

        /* Animations */
        .fade-in {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.8s ease, transform 0.8s ease;
        }

        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .slide-in-right {
            opacity: 0;
            transform: translateX(50px);
            transition: opacity 0.8s ease, transform 0.8s ease;
        }

        .slide-in-right.visible {
            opacity: 1;
            transform: translateX(0);
        }

        .slide-in-left {
            opacity: 0;
            transform: translateX(-50px);
            transition: opacity 0.8s ease, transform 0.8s ease;
        }

        .slide-in-left.visible {
            opacity: 1;
            transform: translateX(0);
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .hero-content,
            .about-content,
            .contact-container {
                grid-template-columns: 1fr;
                gap: 3rem;
            }

            .hero-text {
                text-align: center;
            }

            .hero-text h1 {
                font-size: 2.8rem;
            }

            .social-icons {
                gap: 1.5rem;
            }
        }

        @media (max-width: 768px) {
            .nav-links {
                position: fixed;
                top: 80px;
                right: 0;
                width: 100%;
                height: calc(100vh - 80px);
                background: var(--bg-primary);
                flex-direction: column;
                justify-content: flex-start;
                padding: 2rem;
                transform: translateX(100%);
                transition: var(--transition);
                box-shadow: var(--shadow-lg);
            }

            .nav-links.active {
                transform: translateX(0);
            }

            .mobile-menu {
                display: block;
            }

            .hero-text h1 {
                font-size: 2.2rem;
            }

            .section {
                padding: 60px 0;
            }

            .section-title {
                font-size: 2rem;
            }

            .hero-buttons {
                justify-content: center;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .social-icons {
                gap: 1rem;
            }
            
            .social-icon {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }
            
            .admin-link {
                padding: 0.4rem 0.8rem;
                font-size: 0.8rem;
            }
        }

        @media (max-width: 480px) {
            .container,
            .container-fluid {
                padding: 0 15px;
            }

            .hero-text h1 {
                font-size: 1.8rem;
            }

            .agency-image {
                width: 100%;
            }

            .about-stats {
                grid-template-columns: 1fr;
            }
            
            .portfolio-container {
                grid-template-columns: 1fr;
            }
            
            .contact-container {
                grid-template-columns: 1fr;
            }
        }
        
        /* لوحة التحكم */
        #dashboard {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--bg-primary);
            z-index: 2000;
            overflow-y: auto;
        }

        .dashboard {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
        }

        .sidebar {
            background: var(--bg-secondary);
            border-right: 1px solid var(--bg-tertiary);
            padding: 1.5rem;
        }

        .sidebar-header {
            padding: 1rem 0;
            border-bottom: 1px solid var(--bg-tertiary);
            margin-bottom: 1.5rem;
        }

        .sidebar-logo {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--primary);
            text-align: center;
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-menu li {
            margin-bottom: 0.5rem;
        }

        .sidebar-menu a {
            display: block;
            padding: 0.8rem 1rem;
            border-radius: var(--border-radius);
            text-decoration: none;
            color: var(--text-primary);
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: var(--primary);
            color: white;
        }

        .main-content {
            padding: 2rem;
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--bg-tertiary);
        }

        .dashboard-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .dashboard-header .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .dashboard-header .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--gradient-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .dashboard-header .user-name {
            font-weight: 600;
        }
        
        .dashboard-header .logout-btn {
            background: var(--danger);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
        }
        
        .dashboard-header .logout-btn:hover {
            background: #c0392b;
        }

        /* Stats */
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--bg-secondary);
            padding: 1.5rem;
            border-radius: var(--border-radius-lg);
            text-align: center;
            box-shadow: var(--shadow-sm);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 1rem;
        }

        .content-section {
            background: var(--bg-secondary);
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-sm);
            margin-bottom: 2rem;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            text-align: right;
            margin: 0;
            background: none;
            -webkit-background-clip: unset;
            background-clip: unset;
            color: var(--primary);
        }

        .btn-add {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.7rem 1.5rem;
            border-radius: var(--border-radius);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
        }

        .btn-add:hover {
            background: var(--primary-dark);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            overflow: hidden;
        }

        th, td {
            padding: 1rem;
            text-align: right;
            border-bottom: 1px solid var(--bg-tertiary);
        }

        th {
            background: var(--primary);
            color: white;
            font-weight: 600;
        }

        tr:hover {
            background: var(--bg-tertiary);
        }

        .actions {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
        }

        .btn-edit, .btn-delete {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-edit {
            background: var(--accent);
            color: white;
        }

        .btn-delete {
            background: var(--danger);
            color: white;
        }

        .btn-edit:hover, .btn-delete:hover {
            transform: scale(1.1);
        }

        .form-container {
            background: var(--bg-primary);
            padding: 2rem;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-sm);
            max-width: 700px;
            margin: 0 auto;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-group-full {
            grid-column: span 2;
        }

        .image-preview {
            width: 150px;
            height: 150px;
            border: 2px dashed var(--bg-tertiary);
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            overflow: hidden;
        }

        .image-preview img {
            max-width: 100%;
            max-height: 100%;
            display: none;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1rem;
        }

        .btn-cancel {
            background: var(--text-muted);
            color: white;
        }

        .btn-submit {
            background: var(--success);
            color: white;
        }
        
        /* Multiselect */
        .form-group {
            direction: rtl;
            max-width: 500px;
            margin: auto;
            font-family: 'Tajawal', sans-serif;
        }

        .form-label {
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 10px;
            color: #333;
            display: block;
        }

        .custom-multiselect {
            border: 1px solid #ccc;
            border-radius: 8px;
            padding: 12px;
            cursor: pointer;
            background-color: #fff;
            position: relative;
            color: #333;
            font-size: 16px;
        }

        .options-list {
            display: none;
            border: 1px solid #ccc;
            border-top: none;
            position: absolute;
            top: 100%;
            right: 0;
            width: 100%;
            max-height: 250px;
            overflow-y: auto;
            background-color: white;
            z-index: 100;
            box-shadow: 0px 2px 8px rgba(0, 0, 0, 0.1);
            border-radius: 0 0 8px 8px;
        }

        .options-list label {
            display: block;
            padding: 10px;
            cursor: pointer;
        }

        .options-list label:hover {
            background-color: #f0f0f0;
        }

        .options-list input[type="checkbox"] {
            margin-left: 8px;
            accent-color: #007bff;
        }

        .show {
            display: block;
        }
        
        /* Preloader */
        .preloader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--bg-primary);
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: opacity 0.5s ease;
        }
        
        .preloader-content {
            text-align: center;
        }
        
        .preloader-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid var(--bg-tertiary);
            border-top: 5px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Success message */
        .success-message {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--success);
            color: white;
            padding: 15px 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            z-index: 10000;
            display: flex;
            align-items: center;
            gap: 10px;
            transform: translateX(150%);
            transition: transform 0.3s ease;
        }
        
        .success-message.show {
            transform: translateX(0);
        }
        img {
    max-width: 100%;
    height: auto;
    display: block;
}

#sendWhatsApp {
  background-color: #25D366; /* اللون الأخضر للواتساب */
  color: white;
  border: none;
  width: 100%;
  padding: 12px;
  font-size: 16px;
  border-radius: 8px;
  cursor: pointer;
  transition: background-color 0.3s ease;
}

#sendWhatsApp i {
  margin-left: 8px;
}

#sendWhatsApp:hover {
  background-color: #1ebe5d;
}
.fly-effect img {
    animation: flyUpDown 3s ease-in-out infinite;
    transition: transform 0.3s ease-in-out;
}

@keyframes flyUpDown {
    0%   { transform: translateY(0); }
    50%  { transform: translateY(-15px); }
    100% { transform: translateY(0); }
}
  .footer .container {
    max-width: 900px;
    margin: 0 auto;
    padding: 20px;
    text-align: center;
  }
  .social-icons, .countries-icons, .payment-icons {
    display: flex;
    gap: 12px;
    justify-content: center;
    flex-wrap: wrap;
    margin-bottom: 20px;
  }
  .icon-circle, .social-link {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 0 5px rgba(0,0,0,0.1);
    font-size: 20px;
  }
  .icon-circle img {
    width: 28px;
    height: 28px;
    object-fit: contain;
  }
  /* ألوان الأيقونات الاجتماعية */
  .social-link.facebook { background-color: #3b5998; color: #fff; }
  .social-link.twitter { background-color: #1da1f2; color: #fff; }
  .social-link.instagram { background-color: #e4405f; color: #fff; }
  .social-link.linkedin { background-color: #0077b5; color: #fff; }
  .social-link.youtube { background-color: #ff0000; color: #fff; }
  .social-link.whatsapp { background-color: #25D366; color: #fff; }
  .social-link.telegram { background-color: #0088cc; color: #fff; }

  /* أيقونات الدفع (خلفية رمادية فاتحة) */
  .payment-icons .icon-circle {
    background-color: #f5f5f5;
  }
  /* أيقونات الدول (خلفية رمادية فاتحة) */
  .countries-icons .icon-circle {
    background-color: #f5f5f5;
  }

  footer p {
    margin: 6px 0;
    font-size: 14px;
    color: #555;
  }
  footer i.fa-heart {
    color: #e25555;
  }
  /* الوضع العادي (الافتراضي) */
body {
    background-color: #f9fafb;
    color: #111827;
}

/* الوضع الليلي */
body.dark-mode {
    background-color: #1e293b;
    color: #f1f5f9;
}

/* تخصيص مدخلات النموذج للوضع الليلي */
body.dark-mode .form-control {
    background-color: #334155;
    color: #f8fafc;
    border: 1px solid #475569;
}

body.dark-mode .form-label {
    color: #cbd5e1;
}

/* تخصيص الزر */
.theme-toggle {
    background: none;
    border: none;
    color: inherit;
    font-size: 1.2rem;
    cursor: pointer;
}
/* يجعل كل الصور متجاوبة */
img {
    max-width: 100%;
    height: auto;
    display: block;
    border-radius: 8px; /* يعطي مظهر ناعم للصورة */
    object-fit: cover;
}

/* الحاوية اللي تحتوي الصور تكون مرنة */
.picture-container,
.image-box,
.responsive-image,
.service-image,
.card img,
.gallery img {
    width: 100%;
    max-width: 100%;
    margin: auto;
    padding: 10px;
    box-sizing: border-box;
}

/* تحسين عرض الصور على الشاشات الصغيرة */
@media (max-width: 768px) {
    img {
        border-radius: 6px;
    }

    .picture-container,
    .image-box {
        padding: 5px;
    }
}

/* تحسين عرض الصور على الشاشات الصغيرة جدًا */
@media (max-width: 480px) {
    .picture-container,
    .image-box {
        padding: 2px;
    }
}
    </style>
</head>
<body>
    <!-- صفحة تسجيل الدخول -->
    <div id="login-page" class="login-container" style="display: none;">
        <div class="login-form">
            <button class="login-close" id="login-close">
                <i class="fas fa-times"></i>
            </button>
            
            <div class="login-logo">
                <h1 style="font-weight: bold; font-size: 48px;">
  <span style="color: #2D6DFC;">C</span><span style="color: #FF8800;">M</span><span style="color: #7D4DFF;">D</span>
</h1>
                <p>لوحة تحكم المؤسسة</p>
            </div>
            
            <form id="login-form">
                <div class="form-group">
                    <label for="email_main" class="form-label" >البريد الإلكتروني</label>
                    <input type="email" id="email_main" name="email" class="form-control" required>
                </div>
                <br>
                <div class="form-group">
                    <label for="password" class="form-label">كلمة المرور</label>
                    <input type="password" id="password" class="form-control" required>
                </div>
                <br>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-sign-in-alt"></i>
                        تسجيل الدخول
                    </button>
                </div>
                
                <div class="alert alert-error" id="login-error" style="display: none;">
                    بيانات الدخول غير صحيحة. يرجى المحاولة مرة أخرى.
                </div>
            </form>
        </div>
    </div>

    <!-- Scroll Progress -->
    <div class="scroll-progress" id="scrollProgress"></div>
    
    <!-- Preloader -->
    <div class="preloader" id="preloader">
        <div class="preloader-content">
            <div class="preloader-spinner"></div>
            <p>جاري التحميل...</p>
        </div>
    </div>
    
    <!-- Success Message -->
    <div class="success-message" id="success-message">
        <i class="fas fa-check-circle"></i>
        <span id="success-text">تم الحفظ بنجاح</span>
    </div>

    <!-- Header -->
    <header class="header" id="header">
        <div class="container">
            <div class="header-content">
                <a href="#home" class="logo">
                    <span class="logo-text">
  <span style="color: #2D6DFC;">C</span><span style="color: #FF8800;">M</span><span style="color: #7D4DFF;">D</span>
</span>
                </a>
                
                <nav class="nav">
                   <ul class="nav-links" id="navLinks">
    <li><a href="#home" class="nav-link active"><i class="fas fa-home"></i> الرئيسية</a></li>
    <li><a href="#about" class="nav-link"><i class="fas fa-info-circle"></i> عن المؤسسة</a></li>
    <li><a href="#services" class="nav-link"><i class="fas fa-concierge-bell"></i> خدماتنا</a></li>
    <li><a href="#portfolio" class="nav-link"><i class="fas fa-briefcase"></i> أعمالنا</a></li>
    <li><a href="#social" class="nav-link"><i class="fas fa-share-alt"></i> وسائل التواصل</a></li>
    <li><a href="#process" class="nav-link"><i class="fas fa-cogs"></i> عملية العمل</a></li>
    <li><a href="#contact" class="nav-link"><i class="fas fa-envelope"></i> تواصل معنا</a></li>
    
    <li id="admin-link-container"><a href="#" class="admin-link" id="admin-link"><i class="fas fa-cog"></i> لوحة التحكم</a></li>
    
</ul>
                    
                    <button class="theme-toggle" id="themeToggle" aria-label="تبديل الوضع المظلم">
                        <i class="fas fa-moon"></i>
                    </button>
                    
                    <button class="mobile-menu" id="mobileMenu" aria-label="فتح القائمة">
                        <i class="fas fa-bars"></i>
                    </button>
                </nav>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section id="home" class="hero">
        <div class="container">
            <div class="hero-content">
                <div class="hero-text fade-in">
                   <h1 style="font-weight: bold; font-size: 36px;">
  وكالة
  <span style="display: inline;">
    <span style="color: #2D6DFC; display: inline;">C</span><span style="color: #FF8800; display: inline;">M</span><span style="color: #7D4DFF; display: inline;">D</span>
  </span>
  للإبداع الرقمي
</h1>
                    <div class="typing-text" id="typingText"></div>
                    <p>مؤسسة متخصصة في تقديم حلول رقمية متكاملة تشمل تصميم المواقع، أنظمة الإدارة، الشعارات، الإعلانات، وإدارة وسائل التواصل الاجتماعي. نقدم خدمات احترافية تلبي احتياجاتك في العصر الرقمي.</p>
                    
                    <div class="hero-buttons">
                        <a href="#services" class="btn btn-primary">
                            <i class="fas fa-briefcase"></i>
                            اكتشف خدماتنا
                        </a>
                        <a href="#contact" class="btn btn-outline">
                            <i class="fas fa-envelope"></i>
                            تواصل معنا
                        </a>
                    </div>
                </div>
                
                <div class="hero-image fly-effect">
    <img src="file_00000000bacc61f884a69b1be32458e7.png" alt="">
</div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="section">
        <div class="container">
            <h2 class="section-title fade-in">
  عن مؤسسة
  <span style="display: inline;">
    <span style="color: #2D6DFC; display: inline;">C</span><span style="color: #FF8800; display: inline;">M</span><span style="color: #7D4DFF; display: inline;">D</span>
  </span>
</h2>
            <p class="section-subtitle fade-in">اكتشف من نحن وما نقدمه من حلول رقمية متكاملة</p>
            
            <div class="about-content">
                <div class="about-text fade-in">
<p>
  تأسست مؤسسة 
  <span style="display: inline;">
    <span style="color: #2D6DFC; display: inline;">C</span><span style="color: #FF8800; display: inline;">M</span><span style="color: #7D4DFF; display: inline;">D</span>
  </span>
  (Code Marketing Design) عام 2018 بهدف تقديم حلول رقمية متكاملة للشركات والأفراد. نحن نؤمن بأن الحلول الرقمية يجب أن تكون متميزة وسهلة الاستخدام وقابلة للتطوير.
</p>
                    
                    <p>نحن نقدم مجموعة واسعة من الخدمات التي تغطي كافة جوانب الحلول الرقمية، بدءًا من تصميم المواقع وتطويرها، مرورًا بتصميم الشعارات والهويات البصرية، وصولاً إلى إدارة وسائل التواصل الاجتماعي والتسويق الرقمي.</p>
                    
                    <p>هدفنا هو مساعدة عملائنا على تحقيق أهدافهم الرقمية من خلال تقديم حلول مبتكرة وفعالة، مع التركيز على الجودة والاحترافية في كل ما نقدمه.</p>
                    
                    <div class="about-stats">
                        <div class="stat-item">
                            <div class="stat-number" data-count="250">0</div>
                            <div class="stat-label">مشروع مكتمل</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number" data-count="120">0</div>
                            <div class="stat-label">عميل راضي</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number" data-count="6">0</div>
                            <div class="stat-label">سنوات خبرة</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number" data-count="15">0</div>
                            <div class="stat-label">خدمة متخصصة</div>
                        </div>
                    </div>
                </div>
                
                <div class="about-info slide-in-right">
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-building"></i>
                            </div>
                            <div>
                                <strong>اسم المؤسسة:</strong> CMD - Code Marketing Design
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div>
                                <strong>سنة التأسيس:</strong> 2018
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div>
                                <strong>الموقع:</strong> الرياض، المملكة العربية السعودية
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div>
                                <strong>فريق العمل:</strong> 20+ متخصص
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div>
                                <strong>البريد:</strong> info@cmd-agency.com
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div>
                                <strong>الهاتف:</strong> +966 11 123 4567
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="section">
        <div class="container">
            <h2 class="section-title fade-in">خدماتنا المتخصصة</h2>
            <p class="section-subtitle fade-in">نقدم مجموعة واسعة من الخدمات الرقمية المتكاملة</p>
            
            <div class="services-container" id="services-container">
                <?php foreach ($services as $service): ?>
                <div class="service-card fade-in">
                    <div class="service-icon">
                        <i class="<?php echo htmlspecialchars($service['icon']); ?>"></i>
                    </div>
                    <h3 class="service-title"><?php echo htmlspecialchars($service['title']); ?></h3>
                    <p class="service-description"><?php echo htmlspecialchars($service['description']); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Portfolio Section -->
    <section id="portfolio" class="section">
        <div class="container">
            <h2 class="section-title fade-in">أعمالنا المميزة</h2>
            <p class="section-subtitle fade-in">مجموعة من أفضل المشاريع التي عملنا عليها مؤخراً</p>
            
            <div class="portfolio-container" id="portfolio-container">
                <?php foreach ($projects as $project): ?>
                <div class="portfolio-item fade-in">
                    <div class="portfolio-image">
                        <?php if ($project['image']): ?>
                            <img src="<?php echo htmlspecialchars($project['image']); ?>" alt="<?php echo htmlspecialchars($project['title']); ?>">
                        <?php else: ?>
                            <i class="fas fa-image"></i>
                        <?php endif; ?>
                    </div>
                    <div class="portfolio-content">
                        <span class="portfolio-category"><?php echo htmlspecialchars($project['category']); ?></span>
                        <h3 class="portfolio-title"><?php echo htmlspecialchars($project['title']); ?></h3>
                        <p class="portfolio-description"><?php echo htmlspecialchars($project['description']); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Social Media Section -->
    <section id="social" class="section social-media">
        <div class="container">
            <h2 class="section-title fade-in">وسائل التواصل الاجتماعي</h2>
            <p class="section-subtitle fade-in">نقدم خدمات متكاملة لجميع منصات التواصل الاجتماعي</p>
            
            <div class="social-icons">
                <div class="social-icon facebook">
                    <i class="fab fa-facebook-f"></i>
                </div>
                <div class="social-icon twitter">
                    <i class="fab fa-twitter"></i>
                </div>
                <div class="social-icon instagram">
                    <i class="fab fa-instagram"></i>
                </div>
                <div class="social-icon tiktok">
                    <i class="fab fa-tiktok"></i>
                </div>
                <div class="social-icon youtube">
                    <i class="fab fa-youtube"></i>
                </div>
                <div class="social-icon linkedin">
                    <i class="fab fa-linkedin-in"></i>
                </div>
                <div class="social-icon snapchat">
                    <i class="fab fa-snapchat-ghost"></i>
                </div>
                <div class="social-icon whatsapp" style="background-color: #25D366; color: #fff;">
  <i class="fab fa-whatsapp"></i>
</div>
<div class="social-icon telegram" style="background-color: #0088cc; color: #fff;">
  <i class="fab fa-telegram-plane"></i>
</div>
            </div>
            
            <div class="about-stats" style="margin-top: 4rem;">
                <div class="stat-item fade-in">
                    <div class="stat-number" data-count="50">0</div>
                    <div class="stat-label">حملة تسويقية</div>
                </div>
                <div class="stat-item fade-in">
                    <div class="stat-number" data-count="1200">0</div>
                    <div class="stat-label">فيديو مونتاج</div>
                </div>
                <div class="stat-item fade-in">
                    <div class="stat-number" data-count="85">0</div>
                    <div class="stat-label">حساب تم توثيقه</div>
                </div>
                <div class="stat-item fade-in">
                    <div class="stat-number" data-count="500">0</div>
                    <div class="stat-label">تصميم إعلان</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Process Section -->
    <section id="process" class="section">
        <div class="container">
            <h2 class="section-title fade-in">كيف نعمل معاً</h2>
            <p class="section-subtitle fade-in">عملية منظمة تضمن تحقيق أهدافك الرقمية</p>
            
            <div class="process-container">
                <div class="process-step fade-in">
                    <div class="step-number">1</div>
                    <h3 class="step-title">الاستشارة والتخطيط</h3>
                    <p class="step-description">نبدأ بفهم متطلباتك وأهدافك، ثم نضع خطة عمل واضحة تحدد الخطوات والمواعيد النهائية.</p>
                </div>
                
                <div class="process-step fade-in">
                    <div class="step-number">2</div>
                    <h3 class="step-title">التصميم والتطوير</h3>
                    <p class="step-description">ننتقل إلى مرحلة التصميم والتطوير، مع تقديم عينات دورية لضمان موافقتك على المسار.</p>
                </div>
                
                <div class="process-step fade-in">
                    <div class="step-number">3</div>
                    <h3 class="step-title">المراجعة والتعديل</h3>
                    <p class="step-description">نقدم لك المشروع النهائي للمراجعة، ونقوم بإجراء أي تعديلات مطلوبة لضمان رضاك التام.</p>
                </div>
                
                <div class="process-step fade-in">
                    <div class="step-number">4</div>
                    <h3 class="step-title">التسليم والدعم</h3>
                    <p class="step-description">نسلم المشروع مع جميع المستندات والضمانات، ونقدم دعمًا فنيًا مستمرًا لضمان نجاحه.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="section">
        <div class="container">
            <h2 class="section-title fade-in">تواصل معنا</h2>
            <p class="section-subtitle fade-in">هل لديك مشروع في الذهن؟ دعنا نحوله إلى واقع</p>
            
            <div class="contact-container">
                <div class="contact-info fade-in">
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div>
                            <h4>العنوان</h4>
                            <p>صنعاء،   اليمن
                                <br>
                                الرياض ،المملكة العربية السعودية
                            </p>
                        </div>
                    </div>
                    
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div>
                            <h4>الهاتف</h4>
                            <p>+967 738 780 388</p>
                        </div>
                    </div>
                    
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div>
                            <h4>البريد الإلكتروني</h4>
                            <p>info@cmd-agency.com</p>
                        </div>
                    </div>
                    
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div>
                            <h4>ساعات العمل</h4>
                          <p>متاحون على مدار الساعة - جميع أيام الأسبوع</p>
                        </div>
                    </div>
                </div>
                
                <form class="contact-form slide-in-right" id="contactForm">
                    <div class="form-group">
                        <label for="name" class="form-label">الاسم الكامل *</label>
                        <input type="text" id="name" name="name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email" class="form-label">البريد الإلكتروني *</label>
                        <input type="email" id="email" name="email" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
    <label class="form-label">نوع الخدمة *</label>
    <div class="custom-multiselect" onclick="toggleOptions(event)">
        <span id="selected-options">اضغط لاختيار الخدمات</span>
        <div id="options" class="options-list">
            <label><input type="checkbox" name="services[]" value="تصميم المواقع"> تصميم المواقع</label>
            <label><input type="checkbox" name="services[]" value="الشعارات"> الشعارات</label>
            <label><input type="checkbox" name="services[]" value="لوح اعلانيه"> لوح إعلانية</label>
            <label><input type="checkbox" name="services[]" value="تصميم انظمه"> تصميم أنظمة</label>
            <label><input type="checkbox" name="services[]" value="كروت للموظفين"> كروت للموظفين</label>
            <label><input type="checkbox" name="services[]" value="كرت id"> كرت ID</label>
            <label><input type="checkbox" name="services[]" value="كتلوج"> كتالوج</label>
            <label><input type="checkbox" name="services[]" value="استضافه مواقع"> استضافة مواقع</label>
            <label><input type="checkbox" name="services[]" value="أداره حسابات تواصل اجتماعي"> إدارة حسابات تواصل اجتماعي</label>
            <label><input type="checkbox" name="services[]" value="توثيق حسابات تواصل اجتماعي"> توثيق حسابات تواصل اجتماعي</label>
            <label><input type="checkbox" name="services[]" value="اخرى"> أخرى</label>
        </div>
    </div>
</div>
                    
                    <div class="form-group">
                        <label for="message" class="form-label">الرسالة *</label>
                        <textarea id="message" name="message" class="form-control" rows="5" required></textarea>
                    </div>
                    
                 <button type="button" id="sendWhatsApp">
  <i class="fab fa-whatsapp"></i>
  إرسال عبر واتساب
</button>
                </form>
            </div>
        </div>
    </section>

    <!-- Footer -->
<footer class="footer">
  <div class="container">

    <!-- أيقونات التواصل الاجتماعي -->
    <div class="social-icons">
      <a href="#" class="social-link facebook" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
      <a href="#" class="social-link twitter" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
      <a href="#" class="social-link instagram" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
      <a href="#" class="social-link linkedin" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
      <a href="#" class="social-link youtube" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
      <a href="#" class="social-link whatsapp" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a>
      <a href="#" class="social-link telegram" aria-label="Telegram"><i class="fab fa-telegram-plane"></i></a>
    </div>

    <!-- أيقونات الدول -->
    <div class="countries-icons" aria-label="الدول المقدمة فيها الخدمات" role="region" tabindex="0">
      <div class="icon-circle" title="اليمن"><img src="https://flagcdn.com/ye.svg" alt="علم اليمن"></div>
      <div class="icon-circle" title="المملكة العربية السعودية"><img src="https://flagcdn.com/sa.svg" alt="علم السعودية"></div>
      <div class="icon-circle" title="الإمارات"><img src="https://flagcdn.com/ae.svg" alt="علم الإمارات"></div>
      <div class="icon-circle" title="قطر"><img src="https://flagcdn.com/qa.svg" alt="علم قطر"></div>
      <div class="icon-circle" title="البحرين"><img src="https://flagcdn.com/bh.svg" alt="علم البحرين"></div>
      <div class="icon-circle" title="الكويت"><img src="https://flagcdn.com/kw.svg" alt="علم الكويت"></div>
      <div class="icon-circle" title="عمان"><img src="https://flagcdn.com/om.svg" alt="علم عمان"></div>
      <div class="icon-circle" title="مصر"><img src="https://flagcdn.com/eg.svg" alt="علم مصر"></div>
      <div class="icon-circle" title="الأردن"><img src="https://flagcdn.com/jo.svg" alt="علم الأردن"></div>
      <div class="icon-circle" title="العراق"><img src="https://flagcdn.com/iq.svg" alt="علم العراق"></div>
      <div class="icon-circle" title="الولايات المتحدة الأمريكية"><img src="https://flagcdn.com/us.svg" alt="علم الولايات المتحدة"></div>
      <div class="icon-circle" title="ألمانيا"><img src="https://flagcdn.com/de.svg" alt="علم ألمانيا"></div>
    </div>

    <!-- أيقونات طرق الدفع -->
    <div class="payment-icons" aria-label="طرق الدفع" role="region" tabindex="0">
  <div class="icon-circle" title="باي بال">
    <img src="https://upload.wikimedia.org/wikipedia/commons/b/b5/PayPal.svg" alt="PayPal" />
  </div>
  <div class="icon-circle" title="ماستر كارد">
    <img src="https://upload.wikimedia.org/wikipedia/commons/2/2a/Mastercard-logo.svg" alt="MasterCard" />
  </div>
  <div class="icon-circle" title="الراجحي">
    <a href="https://www.alrajhibank.com.sa" target="_blank" rel="noopener noreferrer">
      <img src="https://th.bing.com/th/id/R.1916f176b6b848dee82af39b10735850?rik=VDsXNHc9alK3Ag&pid=ImgRaw&r=0" alt="الراجحي" />
    </a>
  </div>
  <div class="icon-circle" title="الكريمي">
    <a href="https://kcb-ye.com" target="_blank" rel="noopener noreferrer">
      <img src="https://tse1.mm.bing.net/th/id/OIP.sl6x6N1BOeM8zj80IqOLKgHaHa?r=0&rs=1&pid=ImgDetMain&o=7&rm=3" alt="الكريمي" style="width: 28px; height: auto;" />
    </a>
  </div>
  </div>
</div>

   <div class="sidebar-logo" style="font-weight: bold; font-size: 26px; text-align: center; font-family: 'Segoe UI', sans-serif;">
  <span style="color: #2D6DFC;">C</span><span style="color: #FF8800;">M</span><span style="color: #7D4DFF;">D</span>
</div>
    <p>صُنع بـ <i class="fas fa-heart" style="color: var(--secondary);"></i> في المملكة العربية السعودية</p>

  </div>
</footer>

    <!-- Scroll to Top Button -->
    <button class="scroll-top" id="scrollTop" aria-label="العودة للأعلى">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- لوحة التحكم -->
    <div id="dashboard">
        <div class="dashboard">
            <!-- الشريط الجانبي -->
            <div class="sidebar">
                <div class="sidebar-header">
                    <div class="sidebar-logo" style="font-weight: bold; font-size: 30px; text-align: center;">
    <span style="color: #2D6DFC;">C</span><span style="color: #FF8800;">M</span><span style="color: #7D4DFF;">D</span>
</div>
                </div>
                
                <ul class="sidebar-menu">
                    <li><a href="#dashboard-home" class="active"><i class="fas fa-home"></i> الرئيسية</a></li>
                    <li><a href="#dashboard-services"><i class="fas fa-briefcase"></i> الخدمات</a></li>
                    <li><a href="#dashboard-projects"><i class="fas fa-project-diagram"></i> المشاريع</a></li>
                    <li><a href="#dashboard-settings"><i class="fas fa-cog"></i> الإعدادات</a></li>
                </ul>
            </div>
            
            <!-- المحتوى الرئيسي -->
            <div class="main-content">
                <div class="dashboard-header">
                    <h2 class="dashboard-title" id="dashboard-title">لوحة التحكم</h2>
                    <div class="user-info">
                        <div class="user-avatar">م</div>
                        <div class="user-name">مدير النظام</div>
                        <button class="logout-btn" id="logout-btn">
                            <i class="fas fa-sign-out-alt"></i> تسجيل الخروج
                        </button>
                    </div>
                </div>
                
                <!-- القسم الرئيسي -->
                <div id="dashboard-home">
                    <div class="stats">
                        <div class="stat-card">
                            <div class="stat-number" id="services-count"><?php echo count($services); ?></div>
                            <div class="stat-label">الخدمات</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number" id="projects-count"><?php echo count($projects); ?></div>
                            <div class="stat-label">المشاريع</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number" id="categories-count"><?php echo count($categories); ?></div>
                            <div class="stat-label">التصنيفات</div>
                        </div>
                    </div>
                    
                    <div class="content-section">
                        <div class="section-header">
                            <h3 class="section-title">الخدمات المضافة</h3>
                        </div>
                        
                        <div class="services-container" id="home-services">
                            <?php foreach (array_slice($services, 0, 4) as $service): ?>
                            <div class="service-card">
                                <div class="service-icon">
                                    <i class="<?php echo htmlspecialchars($service['icon']); ?>"></i>
                                </div>
                                <h3 class="service-title"><?php echo htmlspecialchars($service['title']); ?></h3>
                                <p class="service-description"><?php echo substr(htmlspecialchars($service['description']), 0, 80); ?>...</p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="content-section">
                        <div class="section-header">
                            <h3 class="section-title">أحدث المشاريع</h3>
                        </div>
                        
                        <div class="portfolio-container" id="home-portfolio">
                            <?php foreach (array_slice($projects, 0, 4) as $project): ?>
                            <div class="portfolio-item">
                                <div class="portfolio-image">
                                    <?php if ($project['image']): ?>
                                        <img src="<?php echo htmlspecialchars($project['image']); ?>" alt="<?php echo htmlspecialchars($project['title']); ?>">
                                    <?php else: ?>
                                        <i class="fas fa-image"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="portfolio-content">
                                    <span class="portfolio-category"><?php echo htmlspecialchars($project['category']); ?></span>
                                    <h3 class="portfolio-title"><?php echo htmlspecialchars($project['title']); ?></h3>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- قسم إدارة الخدمات -->
                <div id="dashboard-services" style="display: none;">
                    <div class="content-section">
                        <div class="section-header">
                            <h3 class="section-title">إدارة الخدمات</h3>
                            <button class="btn btn-primary btn-add" id="add-service">
                                <i class="fas fa-plus"></i> إضافة خدمة
                            </button>
                        </div>
                        
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>الخدمة</th>
                                        <th>التصنيف</th>
                                        <th>الوصف</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody id="services-list">
                                    <?php foreach ($services as $service): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($service['title']); ?></td>
                                        <td><?php echo htmlspecialchars($service['category']); ?></td>
                                        <td><?php echo substr(htmlspecialchars($service['description']), 0, 50); ?>...</td>
                                        <td class="actions">
                                            <button class="btn btn-sm btn-primary edit-service" data-id="<?php echo $service['id']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger delete-service" data-id="<?php echo $service['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>                        
                    </div>
                    
                    <div class="content-section" id="service-form-container" style="display: none;">
                        <div class="section-header">
                            <h3 class="section-title" id="service-form-title">إضافة خدمة جديدة</h3>
                        </div>
                        
                        <form id="service-form">
                            <input type="hidden" id="service-id" value="">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="service-title" class="form-label">عنوان الخدمة</label>
                                    <input type="text" id="service-title" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label for="service-category" class="form-label">التصنيف</label>
                                    <input type="text" id="service-category" class="form-control" required>
                                </div>
                                <div class="form-group-full">
                                    <label for="service-description" class="form-label">وصف الخدمة</label>
                                    <textarea id="service-description" class="form-control" rows="4" required></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="service-icon" class="form-label">أيقونة الخدمة (Font Awesome)</label>
                                    <input type="text" id="service-icon" class="form-control" required>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="button" class="btn btn-cancel" id="cancel-service">إلغاء</button>
                                <button type="submit" class="btn btn-primary btn-submit">حفظ الخدمة</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- قسم إدارة المشاريع -->
                <div id="dashboard-projects" style="display: none;">
                    <div class="content-section">
                        <div class="section-header">
                            <h3 class="section-title">إدارة المشاريع</h3>
                            <button class="btn btn-primary btn-add" id="add-project">
                                <i class="fas fa-plus"></i> إضافة مشروع
                            </button>
                        </div>
                        
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>المشروع</th>
                                        <th>التصنيف</th>
                                        <th>الصورة</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody id="projects-list">
                                    <?php foreach ($projects as $project): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($project['title']); ?></td>
                                        <td><?php echo htmlspecialchars($project['category']); ?></td>
                                        <td>
                                            <?php if ($project['image']): ?>
                                                <img src="<?php echo htmlspecialchars($project['image']); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">
                                            <?php else: ?>
                                                لا توجد صورة
                                            <?php endif; ?>
                                        </td>
                                        <td class="actions">
                                            <button class="btn btn-sm btn-primary edit-project" data-id="<?php echo $project['id']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger delete-project" data-id="<?php echo $project['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="content-section" id="project-form-container" style="display: none;">
                        <div class="section-header">
                            <h3 class="section-title" id="project-form-title">إضافة مشروع جديد</h3>
                        </div>
                        
                        <form id="project-form" enctype="multipart/form-data">
                            <input type="hidden" id="project-id" value="">
                            <input type="hidden" id="existing-image" name="existing_image" value="">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="project-title" class="form-label">عنوان المشروع</label>
                                    <input type="text" id="project-title" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label for="project-category" class="form-label">التصنيف</label>
                                    <input type="text" id="project-category" class="form-control" required>
                                </div>
                                <div class="form-group-full">
                                    <label for="project-description" class="form-label">وصف المشروع</label>
                                    <textarea id="project-description" class="form-control" rows="4" required></textarea>
                                </div>
                                <div class="form-group-full">
                                    <label for="project-image" class="form-label">صورة المشروع</label>
                                    <input type="file" id="project-image" class="form-control" name="image">
                                    <div class="image-preview">
                                        <img id="project-image-preview" src="" alt="معاينة الصورة">
                                        <span>معاينة الصورة</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="button" class="btn btn-cancel" id="cancel-project">إلغاء</button>
                                <button type="submit" class="btn btn-primary btn-submit">حفظ المشروع</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- قسم الإعدادات -->
                <div id="dashboard-settings" style="display: none;">
                    <div class="content-section">
                        <div class="section-header">
                            <h3 class="section-title">إعدادات الحساب</h3>
                        </div>
                        
                        <form id="settings-form">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="user-name" class="form-label">اسم المستخدم</label>
                                    <input type="text" id="user-name" class="form-control" value="<?php echo isset($_SESSION['user']) ? htmlspecialchars($_SESSION['user']['username']) : 'مدير النظام'; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="user-email" class="form-label">البريد الإلكتروني</label>
                                    <input type="email" id="user-email" class="form-control" value="<?php echo isset($_SESSION['user']) ? htmlspecialchars($_SESSION['user']['email']) : 'admin@cmd.com'; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="user-password" class="form-label">كلمة المرور</label>
                                    <input type="password" id="user-password" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label for="user-confirm-password" class="form-label">تأكيد كلمة المرور</label>
                                    <input type="password" id="user-confirm-password" class="form-control">
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary btn-submit">حفظ التغييرات</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ==================== إدارة التطبيق ====================
        let isLoggedIn = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
        
        // ==================== عناصر DOM ====================
        const elements = {
            // الصفحة الرئيسية
            preloader: document.getElementById('preloader'),
            scrollProgress: document.getElementById('scrollProgress'),
            header: document.getElementById('header'),
            navLinks: document.getElementById('navLinks'),
            themeToggle: document.getElementById('themeToggle'),
            mobileMenu: document.getElementById('mobileMenu'),
            typingText: document.getElementById('typingText'),
            scrollTopBtn: document.getElementById('scrollTop'),
            contactForm: document.getElementById('contactForm'),
            servicesContainer: document.getElementById('services-container'),
            portfolioContainer: document.getElementById('portfolio-container'),
            adminLinkContainer: document.getElementById('admin-link-container'),
            adminLink: document.getElementById('admin-link'),
            
            // لوحة التحكم
            loginPage: document.getElementById('login-page'),
            loginClose: document.getElementById('login-close'),
            dashboard: document.getElementById('dashboard'),
            logoutBtn: document.getElementById('logout-btn'),
            servicesCount: document.getElementById('services-count'),
            projectsCount: document.getElementById('projects-count'),
            categoriesCount: document.getElementById('categories-count'),
            servicesList: document.getElementById('services-list'),
            projectsList: document.getElementById('projects-list'),
            homeServices: document.getElementById('home-services'),
            homePortfolio: document.getElementById('home-portfolio'),
            serviceForm: document.getElementById('service-form'),
            projectForm: document.getElementById('project-form'),
            cancelService: document.getElementById('cancel-service'),
            cancelProject: document.getElementById('cancel-project'),
            projectImage: document.getElementById('project-image'),
            projectImagePreview: document.getElementById('project-image-preview'),
            serviceFormContainer: document.getElementById('service-form-container'),
            projectFormContainer: document.getElementById('project-form-container'),
            settingsForm: document.getElementById('settings-form'),
            addServiceBtn: document.getElementById('add-service'),
            addProjectBtn: document.getElementById('add-project'),
            loginForm: document.getElementById('login-form'),
            loginError: document.getElementById('login-error'),
            
            // الرسائل
            successMessage: document.getElementById('success-message'),
            successText: document.getElementById('success-text')
        };

        // ==================== تهيئة التطبيق ====================
        function initApp() {
            // إعداد معالجات الأحداث
            setupEventListeners();
            
            // بدء التأثيرات
            startAnimations();
        }

        // ==================== معالجات الأحداث ====================
        function setupEventListeners() {
            // الصفحة الرئيسية
            window.addEventListener('scroll', handleScroll);
            elements.themeToggle.addEventListener('click', toggleTheme);
            elements.mobileMenu.addEventListener('click', toggleMobileMenu);
            elements.scrollTopBtn.addEventListener('click', scrollToTop);
            elements.contactForm.addEventListener('submit', handleContactForm);
            
            if (elements.adminLink) {
                elements.adminLink.addEventListener('click', showDashboard);
            }
            
            elements.loginClose.addEventListener('click', hideLoginPage);
            
            // لوحة التحكم
            document.querySelectorAll('.sidebar-menu a').forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    showDashboardSection(link.getAttribute('href').substring(1));
                });
            });
            
            if (elements.logoutBtn) {
                elements.logoutBtn.addEventListener('click', logout);
            }
            
            if (elements.addServiceBtn) {
                elements.addServiceBtn.addEventListener('click', showServiceForm);
            }
            
            if (elements.addProjectBtn) {
                elements.addProjectBtn.addEventListener('click', showProjectForm);
            }
            
            if (elements.cancelService) {
                elements.cancelService.addEventListener('click', cancelServiceForm);
            }
            
            if (elements.cancelProject) {
                elements.cancelProject.addEventListener('click', cancelProjectForm);
            }
            
            if (elements.serviceForm) {
                elements.serviceForm.addEventListener('submit', saveService);
            }
            
            if (elements.projectForm) {
                elements.projectForm.addEventListener('submit', saveProject);
            }
            
            if (elements.projectImage) {
                elements.projectImage.addEventListener('change', previewProjectImage);
            }
            
            if (elements.settingsForm) {
                elements.settingsForm.addEventListener('submit', saveSettings);
            }
            
            if (elements.loginForm) {
                elements.loginForm.addEventListener('submit', login);
            }
            
            // إغلاق لوحة تسجيل الدخول بالنقر خارجها
            elements.loginPage.addEventListener('click', (e) => {
                if (e.target === elements.loginPage) {
                    hideLoginPage();
                }
            });
        }

        // ==================== وظائف الصفحة الرئيسية ====================
        function handleScroll() {
            // شريط التقدم
            const scrollTop = window.pageYOffset;
            const docHeight = document.documentElement.scrollHeight - window.innerHeight;
            const scrollPercent = (scrollTop / docHeight) * 100;
            elements.scrollProgress.style.width = scrollPercent + '%';
            
            // زر العودة للأعلى
            if (scrollTop > 300) {
                elements.scrollTopBtn.classList.add('visible');
            } else {
                elements.scrollTopBtn.classList.remove('visible');
            }
            
            // تأثير الهيدر
            if (scrollTop > 100) {
                elements.header.classList.add('scrolled');
            } else {
                elements.header.classList.remove('scrolled');
            }
        }
        
        function toggleTheme() {
            document.body.classList.toggle('dark-mode');
            const isDark = document.body.classList.contains('dark-mode');
            const icon = elements.themeToggle.querySelector('i');
            icon.classList.toggle('fa-moon', !isDark);
            icon.classList.toggle('fa-sun', isDark);
            localStorage.setItem('theme', isDark ? 'dark-mode' : '');
        }
        
        function toggleMobileMenu() {
            elements.navLinks.classList.toggle('active');
            const icon = elements.mobileMenu.querySelector('i');
            icon.classList.toggle('fa-bars');
            icon.classList.toggle('fa-times');
        }
        
        function scrollToTop() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }
        
        function handleContactForm(e) {
            e.preventDefault();
            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الإرسال...';
            submitBtn.disabled = true;
            
            setTimeout(() => {
                alert('تم إرسال رسالتك بنجاح! سوف نتصل بك قريباً.');
                e.target.reset();
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 2000);
        }
        
        function startAnimations() {
            // تأثير الكتابة
            const texts = [
                'حلول رقمية متكاملة',
                'تصميم مواقع احترافية',
                'إدارة وسائل التواصل',
                'تسويق رقمي مبتكر'
            ];
            
            let textIndex = 0;
            let charIndex = 0;
            let isDeleting = false;
            
            function typeText() {
                const currentText = texts[textIndex];
                
                if (isDeleting) {
                    elements.typingText.textContent = currentText.substring(0, charIndex - 1);
                    charIndex--;
                } else {
                    elements.typingText.textContent = currentText.substring(0, charIndex + 1);
                    charIndex++;
                }
                
                let speed = isDeleting ? 50 : 100;
                
                if (!isDeleting && charIndex === currentText.length) {
                    speed = 2000;
                    isDeleting = true;
                } else if (isDeleting && charIndex === 0) {
                    isDeleting = false;
                    textIndex = (textIndex + 1) % texts.length;
                    speed = 500;
                }
                
                setTimeout(typeText, speed);
            }
            
            typeText();
            
            // التأثيرات عند الظهور
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                    }
                });
            }, {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            });
            
            document.querySelectorAll('.fade-in, .slide-in-left, .slide-in-right').forEach(el => {
                observer.observe(el);
            });
            
            // العداد
            const aboutSection = document.getElementById('about');
            const aboutObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        animateCounters();
                        aboutObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.5 });
            
            if (aboutSection) {
                aboutObserver.observe(aboutSection);
            }
        }
        
        function animateCounters() {
            const counters = document.querySelectorAll('[data-count]');
            
            counters.forEach(counter => {
                const target = parseInt(counter.getAttribute('data-count'));
                let count = 0;
                const increment = target / 100;
                
                const updateCount = () => {
                    if (count < target) {
                        count += increment;
                        counter.textContent = Math.ceil(count);
                        setTimeout(updateCount, 20);
                    } else {
                        counter.textContent = target;
                    }
                };
                
                updateCount();
            });
        }

        // ==================== وظائف لوحة التحكم ====================
        function showDashboard(e) {
            if (e) e.preventDefault();
            
            if (!isLoggedIn) {
                showLoginPage();
                return;
            }
            
            document.body.style.overflow = 'hidden';
            elements.dashboard.style.display = 'grid';
            showDashboardSection('dashboard-home');
            
            // تحديث الإحصائيات
            updateDashboardStats();
        }
        
        function hideDashboard() {
            document.body.style.overflow = '';
            elements.dashboard.style.display = 'none';
        }
        
        function showLoginPage() {
            elements.loginPage.style.display = 'flex';
        }
        
        function hideLoginPage() {
            elements.loginPage.style.display = 'none';
        }
        
        function showDashboardSection(sectionId) {
            document.querySelectorAll('.main-content > div').forEach(section => {
                section.style.display = 'none';
            });
            
            document.querySelectorAll('.sidebar-menu a').forEach(link => {
                link.classList.remove('active');
            });
            
            document.getElementById(sectionId).style.display = 'block';
            document.querySelector(`.sidebar-menu a[href="#${sectionId}"]`).classList.add('active');
            document.getElementById('dashboard-title').textContent = document.querySelector(`.sidebar-menu a[href="#${sectionId}"]`).textContent;
        }
        
        function updateDashboardStats() {
            // يمكن تحديث الإحصائيات هنا إذا لزم الأمر
        }
        
        function showServiceForm() {
            elements.serviceForm.reset();
            document.getElementById('service-id').value = '';
            document.getElementById('service-form-title').textContent = 'إضافة خدمة جديدة';
            elements.serviceFormContainer.style.display = 'block';
        }
        
        function showProjectForm() {
            elements.projectForm.reset();
            elements.projectImagePreview.style.display = 'none';
            document.getElementById('project-id').value = '';
            document.getElementById('project-form-title').textContent = 'إضافة مشروع جديد';
            elements.projectFormContainer.style.display = 'block';
        }
        
        function cancelServiceForm() {
            elements.serviceFormContainer.style.display = 'none';
        }
        
        function cancelProjectForm() {
            elements.projectFormContainer.style.display = 'none';
        }
        
        function saveService(e) {
            e.preventDefault();
            
            const id = document.getElementById('service-id').value;
            const title = document.getElementById('service-title').value;
            const category = document.getElementById('service-category').value;
            const description = document.getElementById('service-description').value;
            const icon = document.getElementById('service-icon').value;
            
            const formData = new FormData();
            formData.append('save_service', '1');
            formData.append('id', id);
            formData.append('title', title);
            formData.append('category', category);
            formData.append('description', description);
            formData.append('icon', icon);
            
            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccessMessage('تم حفظ الخدمة بنجاح!');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    alert('حدث خطأ أثناء حفظ الخدمة: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('حدث خطأ أثناء حفظ الخدمة');
            });
        }
        
        function saveProject(e) {
            e.preventDefault();
            
            const id = document.getElementById('project-id').value;
            const title = document.getElementById('project-title').value;
            const category = document.getElementById('project-category').value;
            const description = document.getElementById('project-description').value;
            const existingImage = document.getElementById('existing-image').value;
            
            const formData = new FormData();
            formData.append('save_project', '1');
            formData.append('id', id);
            formData.append('title', title);
            formData.append('category', category);
            formData.append('description', description);
            formData.append('existing_image', existingImage);
            
            if (elements.projectImage.files[0]) {
                formData.append('image', elements.projectImage.files[0]);
            }
            
            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccessMessage('تم حفظ المشروع بنجاح!');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    alert('حدث خطأ أثناء حفظ المشروع: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('حدث خطأ أثناء حفظ المشروع');
            });
        }
        
        function editService(id) {
            fetch('index.php')
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const service = Array.from(doc.querySelectorAll('#services-list tr')).find(tr => {
                    return tr.querySelector('.edit-service').getAttribute('data-id') == id;
                });
                
                if (service) {
                    const title = service.querySelector('td:nth-child(1)').textContent;
                    const category = service.querySelector('td:nth-child(2)').textContent;
                    const description = service.querySelector('td:nth-child(3)').textContent.replace('...', '');
                    const icon = service.querySelector('.service-icon i').className;
                    
                    document.getElementById('service-id').value = id;
                    document.getElementById('service-title').value = title;
                    document.getElementById('service-category').value = category;
                    document.getElementById('service-description').value = description;
                    document.getElementById('service-icon').value = icon;
                    
                    document.getElementById('service-form-title').textContent = 'تعديل الخدمة';
                    elements.serviceFormContainer.style.display = 'block';
                }
            });
        }
        
        function editProject(id) {
            fetch('index.php')
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const project = Array.from(doc.querySelectorAll('#projects-list tr')).find(tr => {
                    return tr.querySelector('.edit-project').getAttribute('data-id') == id;
                });
                
                if (project) {
                    const title = project.querySelector('td:nth-child(1)').textContent;
                    const category = project.querySelector('td:nth-child(2)').textContent;
                    const description = project.querySelector('td:nth-child(4)').textContent;
                    const image = project.querySelector('td:nth-child(3) img')?.src || '';
                    
                    document.getElementById('project-id').value = id;
                    document.getElementById('project-title').value = title;
                    document.getElementById('project-category').value = category;
                    document.getElementById('project-description').value = description;
                    document.getElementById('existing-image').value = image;
                    
                    if (image) {
                        elements.projectImagePreview.src = image;
                        elements.projectImagePreview.style.display = 'block';
                    }
                    
                    document.getElementById('project-form-title').textContent = 'تعديل المشروع';
                    elements.projectFormContainer.style.display = 'block';
                }
            });
        }
        
        function deleteService(id) {
            if (confirm('هل أنت متأكد من رغبتك في حذف هذه الخدمة؟')) {
                const formData = new FormData();
                formData.append('delete_service', '1');
                formData.append('id', id);
                
                fetch('index.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showSuccessMessage('تم حذف الخدمة بنجاح!');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        alert('حدث خطأ أثناء حذف الخدمة: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('حدث خطأ أثناء حذف الخدمة');
                });
            }
        }
        
        function deleteProject(id) {
            if (confirm('هل أنت متأكد من رغبتك في حذف هذا المشروع؟')) {
                const formData = new FormData();
                formData.append('delete_project', '1');
                formData.append('id', id);
                
                fetch('index.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showSuccessMessage('تم حذف المشروع بنجاح!');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        alert('حدث خطأ أثناء حذف المشروع: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('حدث خطأ أثناء حذف المشروع');
                });
            }
        }
        
        function previewProjectImage(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    elements.projectImagePreview.src = e.target.result;
                    elements.projectImagePreview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        }
        
        function saveSettings(e) {
            e.preventDefault();
            
            const username = document.getElementById('user-name').value;
            const email = document.getElementById('user-email').value;
            const password = document.getElementById('user-password').value;
            const confirmPassword = document.getElementById('user-confirm-password').value;
            
            if (password && password !== confirmPassword) {
                alert('كلمة المرور وتأكيدها غير متطابقين');
                return;
            }
            
            const formData = new FormData();
            formData.append('save_settings', '1');
            formData.append('username', username);
            formData.append('email', email);
            formData.append('password', password);
            formData.append('confirm_password', confirmPassword);
            
            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccessMessage('تم حفظ الإعدادات بنجاح!');
                } else {
                    alert('حدث خطأ أثناء حفظ الإعدادات: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('حدث خطأ أثناء حفظ الإعدادات');
            });
        }
        
        function login(e) {
            e.preventDefault();
            
            const email = document.getElementById('email_main').value;
            const password = document.getElementById('password').value;
            
            const formData = new FormData();
            formData.append('login', '1');
            formData.append('email', email);
            formData.append('password', password);
            
            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    isLoggedIn = true;
                    if (elements.adminLinkContainer) {
                        elements.adminLinkContainer.style.display = 'block';
                    }
                    hideLoginPage();
                    showDashboard();
                } else {
                    elements.loginError.style.display = 'block';
                    elements.loginError.textContent = data.message || 'بيانات الدخول غير صحيحة. يرجى المحاولة مرة أخرى.';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                elements.loginError.style.display = 'block';
                elements.loginError.textContent = 'حدث خطأ أثناء محاولة تسجيل الدخول';
            });
        }
        
        function logout() {
            const formData = new FormData();
            formData.append('logout', '1');
            
            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    isLoggedIn = false;
                    if (elements.adminLinkContainer) {
                        elements.adminLinkContainer.style.display = 'none';
                    }
                    hideDashboard();
                    window.location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
        
        function showSuccessMessage(message) {
            elements.successText.textContent = message;
            elements.successMessage.classList.add('show');
            
            setTimeout(() => {
                elements.successMessage.classList.remove('show');
            }, 3000);
        }
        
        function toggleOptions(event) {
            const optionsBox = document.getElementById('options');
            optionsBox.classList.toggle('show');
        }

        // بدء التطبيق عند تحميل الصفحة
        window.addEventListener('load', () => {
            // تحميل الوضع المظلم
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme) {
                document.body.classList.add(savedTheme);
                const icon = elements.themeToggle.querySelector('i');
                icon.classList.toggle('fa-sun', savedTheme === 'dark-mode');
            }
            
            // إخفاء preloader
            setTimeout(() => {
                elements.preloader.style.opacity = '0';
                setTimeout(() => {
                    elements.preloader.style.display = 'none';
                }, 500);
            }, 1000);
            
            // تهيئة التطبيق
            initApp();
            
            // إعداد معالجات الأحداث للخدمات والمشاريع
            document.querySelectorAll('.edit-service').forEach(btn => {
                btn.addEventListener('click', () => editService(btn.getAttribute('data-id')));
            });
            
            document.querySelectorAll('.delete-service').forEach(btn => {
                btn.addEventListener('click', () => deleteService(btn.getAttribute('data-id')));
            });
            
            document.querySelectorAll('.edit-project').forEach(btn => {
                btn.addEventListener('click', () => editProject(btn.getAttribute('data-id')));
            });
            
            document.querySelectorAll('.delete-project').forEach(btn => {
                btn.addEventListener('click', () => deleteProject(btn.getAttribute('data-id')));
            });
        });
        
        // إغلاق القائمة عند الضغط خارجها
        document.addEventListener("click", function (e) {
            const multiselect = document.querySelector('.custom-multiselect');
            if (multiselect && !multiselect.contains(e.target)) {
                document.getElementById('options').classList.remove("show");
            }
        });

        // تحديث النص عند الاختيار
        const checkboxes = document.querySelectorAll('#options input[type="checkbox"]');
        checkboxes.forEach(cb => {
            cb.addEventListener("change", () => {
                const selected = Array.from(checkboxes)
                    .filter(c => c.checked)
                    .map(c => c.parentElement.textContent.trim());

                document.getElementById('selected-options').textContent = selected.length > 0
                    ? selected.join("، ")
                    : "اضغط لاختيار الخدمات";
            });
        });
        
        document.getElementById('sendWhatsApp').addEventListener('click', function () {
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const message = document.getElementById('message').value.trim();

            const selectedServices = [];
            document.querySelectorAll('input[name="services[]"]:checked').forEach(checkbox => {
                selectedServices.push(checkbox.value);
            });

            // التحقق من الحقول
            if (!name || !email || !message || selectedServices.length === 0) {
                alert('يرجى تعبئة جميع الحقول واختيار خدمة واحدة على الأقل.');
                return;
            }

            // صيغة الرسالة
            const fullMessage =
                `📩 *طلب جديد من نموذج الاتصال*\n\n` +
                `👤 *الاسم:* ${name}\n` +
                `📧 *البريد:* ${email}\n` +
                `🛠️ *الخدمات المطلوبة:*\n- ${selectedServices.join('\n- ')}\n\n` +
                `📝 *الرسالة:*\n${message}`;

            // رقم الواتساب (بدون +)
            const phoneNumber = "967738780388";

            // فتح واتساب
            const url = `https://wa.me/${phoneNumber}?text=${encodeURIComponent(fullMessage)}`;
            window.open(url, '_blank');
        });

    </script>
</body>
</html>
