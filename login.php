<?php
/**
 * CRMS SECURE PORTAL (v19.0 - NEWSCASTER ENABLED)
 * -----------------------------------------------
 * - ADDED: News Desk module for Newscasters.
 * - FIXED: Strict Role Permissions (Newscasters cannot see Criminal Data).
 * - FIXED: Admins/Officers cannot see News Desk (unless Admin).
 */

session_start();
// Hide errors from user output
ini_set('display_errors', 0);
ini_set('log_errors', 1); 
error_reporting(E_ALL);

// 1. SAFE DATABASE CONNECTION
$db_host = "localhost"; $db_user = "root"; $db_pass = "";
$conn = null;

try {
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = @new mysqli($db_host, $db_user, $db_pass, "crms_db1"); // Try DB1
    if ($conn->connect_error) {
        $conn = @new mysqli($db_host, $db_user, $db_pass, "crms_db"); // Fallback DB
        if ($conn->connect_error) throw new Exception("DB Connection Failed");
    }
} catch (Exception $e) {
    die("System Error: Database connection failed. Please ensure XAMPP/WAMP is running and 'crms_db' exists.");
}

// 2. AUTO-FIX DATABASE
try {
    // Ensure News Table Exists
    $conn->query("CREATE TABLE IF NOT EXISTS news_feed (
        news_id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255),
        content TEXT,
        type VARCHAR(50), 
        media VARCHAR(255),
        author_id INT,
        is_public TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Fix Columns
    $check = $conn->query("SHOW COLUMNS FROM users LIKE 'face_image'");
    if ($check && $check->num_rows == 0) $conn->query("ALTER TABLE users ADD COLUMN face_image LONGTEXT");
    
    $check2 = $conn->query("SHOW COLUMNS FROM audit_logs LIKE 'ip_address'");
    if ($check2 && $check2->num_rows == 0) $conn->query("ALTER TABLE audit_logs ADD COLUMN ip_address VARCHAR(45)");

} catch (Exception $e) {}

if (!is_dir('uploads')) mkdir('uploads', 0777, true);

// --- HELPER FUNCTIONS ---
function sanitize($conn, $input) { return htmlspecialchars(strip_tags($conn->real_escape_string($input))); }
function get_image($img) {
    if (!empty($img) && file_exists("uploads/" . $img) && $img != 'default.png') return "uploads/" . $img;
    return "https://ui-avatars.com/api/?name=Unknown&background=334155&color=94a3b8&size=256&bold=true";
}
function logAction($conn, $action, $uid = 0) {
    if ($uid == 0 && isset($_SESSION['user_id'])) $uid = $_SESSION['user_id'];
    $act = $conn->real_escape_string($action);
    $ip = $_SERVER['REMOTE_ADDR'];
    try { $conn->query("INSERT INTO audit_logs (user_id, action, ip_address) VALUES ($uid, '$act', '$ip')"); } catch (Exception $e) {}
}

if (!isset($_SESSION['c_n1'])) { $_SESSION['c_n1'] = rand(1,9); $_SESSION['c_n2'] = rand(1,9); }

$msg = ""; $msg_type = "";
$user_id = $_SESSION['user_id'] ?? 0;

// --- ACTIONS ---

// LOGIN
if (isset($_POST['login'])) {
    if ((int)$_POST['captcha'] !== ($_SESSION['c_n1'] + $_SESSION['c_n2'])) {
        $msg = "Security Check Failed."; $msg_type = "error";
    } else {
        $u = sanitize($conn, $_POST['username']);
        $p = $_POST['password'];
        $res = $conn->query("SELECT * FROM users WHERE username='$u'");
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            if (password_verify($p, $row['password'])) {
                if ($row['status'] == 'active') {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $row['user_id'];
                    $_SESSION['role'] = $row['role'];
                    $_SESSION['name'] = $row['full_name'];
                    $_SESSION['unit'] = $row['unit'];
                    try { $conn->query("UPDATE users SET last_login=NOW() WHERE user_id=".$row['user_id']); } catch(Exception $e){}
                    logAction($conn, "Login Successful");
                    header("Location: login.php?page=dashboard"); exit();
                } else { $msg = "Account Pending Approval."; $msg_type = "error"; }
            } else { $msg = "Invalid Password."; $msg_type = "error"; }
        } else { $msg = "User Not Found."; $msg_type = "error"; }
    }
    $_SESSION['c_n1'] = rand(1,9); $_SESSION['c_n2'] = rand(1,9);
}

// REGISTER
if (isset($_POST['register'])) {
    if ((int)$_POST['captcha'] !== ($_SESSION['c_n1'] + $_SESSION['c_n2'])) {
        $msg = "Security Check Failed."; $msg_type = "error";
    } else {
        $fn = sanitize($conn, $_POST['fullname']);
        $un = sanitize($conn, $_POST['username']);
        $pw = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $rl = sanitize($conn, $_POST['role']);
        $check = $conn->query("SELECT * FROM users WHERE username='$un'");
        if ($check && $check->num_rows > 0) { $msg = "Username taken."; $msg_type = "error"; } 
        else {
            $sql = "INSERT INTO users (full_name, username, password, role, status) VALUES ('$fn', '$un', '$pw', '$rl', 'pending')";
            if ($conn->query($sql)) { $msg = "Application Sent. Wait for Admin."; $msg_type = "success"; }
            else { $msg = "Error processing request."; $msg_type = "error"; }
        }
    }
    $_SESSION['c_n1'] = rand(1,9); $_SESSION['c_n2'] = rand(1,9);
}

// SAVE NEWS (Newscaster/Admin)
if (isset($_POST['save_news']) && $user_id) {
    if ($_SESSION['role'] == 'officer') {
        $msg = "Permission Denied."; $msg_type = "error";
    } else {
        $title = sanitize($conn, $_POST['n_title']);
        $type = sanitize($conn, $_POST['n_type']);
        $content = sanitize($conn, $_POST['n_content']);
        
        $media_val = "";
        if (!empty($_FILES['n_media']['name'])) {
            $target = "uploads/" . time() . "_" . basename($_FILES['n_media']['name']);
            if(move_uploaded_file($_FILES['n_media']['tmp_name'], $target)) {
                $media_val = basename($target);
            }
        }
        
        $sql = "INSERT INTO news_feed (title, type, content, media, author_id) VALUES ('$title', '$type', '$content', '$media_val', $user_id)";
        if ($conn->query($sql)) {
            $msg = "News Published Successfully."; $msg_type = "success";
            logAction($conn, "Published News: $title");
        } else {
            $msg = "DB Error: " . $conn->error; $msg_type = "error";
        }
    }
}

// SAVE CRIMINAL (Admin/Officer Only)
if (isset($_POST['save_criminal']) && $user_id) {
    if ($_SESSION['role'] == 'newscaster') {
        $msg = "Permission Denied: Newscasters cannot manage criminal records."; $msg_type = "error";
    } else {
        // Collect Data
        $d = [
            'name' => sanitize($conn, $_POST['c_name']),
            'alias' => sanitize($conn, $_POST['c_alias']),
            'age' => (int)$_POST['c_age'],
            'gender' => sanitize($conn, $_POST['c_gender']),
            'height' => sanitize($conn, $_POST['c_height']),
            'weight' => sanitize($conn, $_POST['c_weight']),
            'eyes' => sanitize($conn, $_POST['c_eyes']),
            'hair' => sanitize($conn, $_POST['c_hair']),
            'scars' => sanitize($conn, $_POST['c_scars']),
            'nat' => sanitize($conn, $_POST['c_nat']),
            'type' => sanitize($conn, $_POST['c_type']),
            'status' => sanitize($conn, $_POST['c_status']),
            'risk' => sanitize($conn, $_POST['c_risk']),
            'gang' => sanitize($conn, $_POST['c_gang']),
            'fp' => sanitize($conn, $_POST['c_fp']),
            'bail' => sanitize($conn, $_POST['c_bail']),
            'evid' => sanitize($conn, $_POST['c_evid']),
            'desc' => sanitize($conn, $_POST['c_desc']),
            'added_by' => $user_id
        ];
        
        $img_val = "default.png";
        $img_sql = "";
        if (!empty($_FILES['c_photo']['name'])) {
            $target = "uploads/" . time() . "_" . basename($_FILES['c_photo']['name']);
            if(move_uploaded_file($_FILES['c_photo']['tmp_name'], $target)) {
                $img_val = basename($target);
                $img_sql = ", mugshot='$img_val'";
            }
        }

        try {
            if (!empty($_POST['edit_id'])) {
                $eid = (int)$_POST['edit_id'];
                $sql = "UPDATE criminals SET full_name='{$d['name']}', alias='{$d['alias']}', age='{$d['age']}', gender='{$d['gender']}', height='{$d['height']}', weight='{$d['weight']}', eye_color='{$d['eyes']}', hair_color='{$d['hair']}', scars_marks='{$d['scars']}', nationality='{$d['nat']}', crime_type='{$d['type']}', status='{$d['status']}', risk_level='{$d['risk']}', gang_affiliation='{$d['gang']}', fingerprint_id='{$d['fp']}', bail_status='{$d['bail']}', evidence_list='{$d['evid']}', description='{$d['desc']}' $img_sql WHERE criminal_id=$eid";
                $op = "Updated";
            } else {
                $sql = "INSERT INTO criminals (full_name, alias, age, gender, height, weight, eye_color, hair_color, scars_marks, nationality, crime_type, status, risk_level, gang_affiliation, fingerprint_id, bail_status, evidence_list, description, mugshot, added_by) 
                        VALUES ('{$d['name']}', '{$d['alias']}', '{$d['age']}', '{$d['gender']}', '{$d['height']}', '{$d['weight']}', '{$d['eyes']}', '{$d['hair']}', '{$d['scars']}', '{$d['nat']}', '{$d['type']}', '{$d['status']}', '{$d['risk']}', '{$d['gang']}', '{$d['fp']}', '{$d['bail']}', '{$d['evid']}', '{$d['desc']}', '$img_val', {$d['added_by']})";
                $op = "Added";
            }

            if ($conn->query($sql)) {
                $msg = "Record $op Successfully."; $msg_type = "success";
                if($op=="Updated") header("Refresh:1; url=login.php?page=database");
            } else { $msg = "DB Error: ".$conn->error; $msg_type = "error"; }
        } catch (Exception $e) { $msg = "System Error: Invalid Data."; $msg_type = "error"; }
    }
}

// LOGOUT
if (isset($_GET['logout'])) { 
    if(session_status() === PHP_SESSION_NONE) session_start();
    logAction($conn, "Logged Out");
    session_destroy();
    header("Location: index.php"); exit(); 
}

// ROUTING
$page = $_GET['page'] ?? 'login';
// Restrict pages based on login status
if (!$user_id && in_array($page, ['dashboard', 'database', 'add_record', 'officers', 'news_desk'])) $page = 'login';

// EDIT DATA FETCH
$edit_data = null;
if ($page == 'add_record' && isset($_GET['edit_id'])) {
    $edit_data = $conn->query("SELECT * FROM criminals WHERE criminal_id=".(int)$_GET['edit_id'])->fetch_assoc();
}

$pending_count = 0;
if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin') {
    try {
        $p_res = $conn->query("SELECT COUNT(*) as c FROM users WHERE status='pending'");
        if($p_res) $pending_count = $p_res->fetch_assoc()['c'];
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <title>CRMS Secure Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { 
                extend: { 
                    fontFamily: { sans: ['Inter','sans-serif'], display: ['Rajdhani','sans-serif'] }, 
                    colors: { darkbg: '#0b1120', panel: '#151e32', accent: '#3b82f6', danger: '#ef4444' } 
                } 
            }
        }
    </script>
    <style>
        .glass { background: rgba(30, 41, 59, 0.8); backdrop-filter: blur(12px); border: 1px solid rgba(59, 130, 246, 0.1); }
        .input-pro { background: #0f172a; border: 1px solid #334155; color: white; transition: 0.2s; }
        .input-pro:focus { border-color: #3b82f6; outline: none; }
    </style>
</head>
<body class="bg-darkbg text-slate-300 font-sans antialiased min-h-screen">

    <?php if($msg): ?>
        <div class="fixed top-5 right-5 z-50 px-6 py-4 rounded bg-panel border-l-4 <?php echo $msg_type=='error'?'border-red-500':'border-green-500'; ?> shadow-lg text-white font-bold animate-bounce flex items-center gap-3">
            <i class="fa-solid <?php echo $msg_type=='error'?'fa-triangle-exclamation':'fa-check-circle'; ?>"></i>
            <?php echo $msg; ?>
        </div>
    <?php endif; ?>

    <?php if (!$user_id): ?>
        <!-- LOGIN SCREEN -->
        <div class="min-h-screen flex items-center justify-center p-4 relative overflow-hidden">
            <div class="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1550751827-4bd374c3f58b?q=80&w=1920')] bg-cover opacity-10"></div>
            <div class="relative z-10 w-full max-w-md glass rounded-2xl shadow-2xl overflow-hidden border border-slate-700/50">
                <div class="bg-gradient-to-r from-blue-900/50 to-slate-900/50 p-8 text-center border-b border-slate-700/50">
                    <i class="fa-solid fa-shield-cat text-5xl text-blue-500 mb-4"></i>
                    <h2 class="font-display text-3xl font-bold uppercase text-white"><?php echo $page=='register'?'Officer Intake':'Secure Login'; ?></h2>
                    <p class="text-xs text-blue-300 uppercase tracking-widest mt-1">National Crime Bureau</p>
                </div>
                
                <div class="p-8">
                    <form method="POST" class="space-y-5">
                        <?php if ($page == 'register'): ?>
                            <div><label class="text-[10px] font-bold uppercase text-slate-500">Full Name</label><input type="text" name="fullname" class="w-full p-3 input-pro rounded" required></div>
                            <div><label class="text-[10px] font-bold uppercase text-slate-500">Role</label><select name="role" class="w-full p-3 input-pro rounded"><option value="officer">Officer</option><option value="newscaster">Media / Press</option></select></div>
                        <?php endif; ?>
                        
                        <div><label class="text-[10px] font-bold uppercase text-slate-500">Badge ID</label><input type="text" name="username" class="w-full p-3 input-pro rounded" required></div>
                        <div><label class="text-[10px] font-bold uppercase text-slate-500">Password</label><input type="password" name="password" class="w-full p-3 input-pro rounded" required></div>
                        
                        <!-- Math Captcha -->
                        <div class="flex items-center justify-between bg-slate-800/50 p-3 rounded border border-slate-700">
                            <span class="text-xs font-mono text-blue-300 font-bold">SECURITY CHECK: <?php echo $_SESSION['c_n1']." + ".$_SESSION['c_n2']; ?> = ?</span>
                            <input type="number" name="captcha" class="w-16 p-1 text-center bg-slate-900 border border-slate-600 rounded text-white font-bold" required>
                        </div>

                        <button type="submit" name="<?php echo $page=='register'?'register':'login'; ?>" class="w-full bg-accent hover:bg-blue-600 text-white font-bold py-3 rounded uppercase tracking-widest transition shadow-lg">
                            <?php echo $page=='register'?'Submit Application':'Authenticate'; ?>
                        </button>
                    </form>
                    
                    <div class="mt-6 text-center text-xs space-y-3 pt-4 border-t border-slate-700/50">
                        <a href="?page=<?php echo $page=='register'?'login':'register'; ?>" class="text-blue-400 hover:text-white font-bold uppercase"><?php echo $page=='register'?'Return to Login':'Apply for Access'; ?></a>
                        <br>
                        <a href="index.php" class="text-slate-500 hover:text-slate-300">Return to Public Portal</a>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- DASHBOARD -->
        <div class="flex h-screen overflow-hidden bg-darkbg">
            <aside class="w-20 lg:w-64 bg-panel flex-shrink-0 flex flex-col border-r border-slate-700/50 z-20">
                <div class="h-20 flex items-center justify-center lg:justify-start lg:px-6 border-b border-slate-700/50 bg-slate-800/50">
                    <i class="fa-solid fa-shield-cat text-accent text-2xl mr-3"></i>
                    <div><h1 class="font-display font-black text-xl text-white tracking-widest leading-none">CRMS</h1><p class="text-[10px] text-slate-500 uppercase font-bold">Secure Net v19</p></div>
                </div>
                
                <!-- Officer Card -->
                <div class="p-6 border-b border-slate-700/50 bg-gradient-to-b from-slate-800/30 to-transparent hidden lg:block">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-blue-600/20 border border-blue-500/50 flex items-center justify-center text-blue-400 font-bold text-lg shadow-lg"><?php echo substr($_SESSION['name'], 0, 1); ?></div>
                        <div class="overflow-hidden">
                            <h4 class="text-white font-bold text-sm truncate"><?php echo $_SESSION['name']; ?></h4>
                            <p class="text-[10px] uppercase font-bold text-slate-400"><?php echo $_SESSION['role']; ?></p>
                        </div>
                    </div>
                </div>

                <nav class="flex-1 overflow-y-auto py-6 px-3 space-y-1">
                    <a href="?page=dashboard" class="flex items-center px-4 py-3 rounded-lg hover:bg-slate-700/50 text-slate-300 hover:text-white transition group <?php echo $page=='dashboard'?'bg-accent/10 text-white border-l-4 border-accent':''; ?>">
                        <i class="fa-solid fa-gauge-high w-6 text-slate-400 group-hover:text-blue-400 transition <?php echo $page=='dashboard'?'text-blue-400':''; ?>"></i> 
                        <span class="font-medium text-sm hidden lg:block ml-2 uppercase tracking-wide">Dashboard</span>
                    </a>

                    <!-- RESTRICTED MENUS: Hide from Newscaster -->
                    <?php if ($_SESSION['role'] != 'newscaster'): ?>
                    <a href="?page=database" class="flex items-center px-4 py-3 rounded-lg hover:bg-slate-700/50 text-slate-300 hover:text-white transition group <?php echo $page=='database'?'bg-accent/10 text-white border-l-4 border-accent':''; ?>">
                        <i class="fa-solid fa-database w-6 text-slate-400 group-hover:text-blue-400 transition <?php echo $page=='database'?'text-blue-400':''; ?>"></i> 
                        <span class="font-medium text-sm hidden lg:block ml-2 uppercase tracking-wide">Records</span>
                    </a>
                    <a href="?page=add_record" class="flex items-center px-4 py-3 rounded-lg hover:bg-slate-700/50 text-slate-300 hover:text-white transition group <?php echo $page=='add_record'?'bg-accent/10 text-white border-l-4 border-accent':''; ?>">
                        <i class="fa-solid fa-user-plus w-6 text-slate-400 group-hover:text-blue-400 transition <?php echo $page=='add_record'?'text-blue-400':''; ?>"></i> 
                        <span class="font-medium text-sm hidden lg:block ml-2 uppercase tracking-wide">New Entry</span>
                    </a>
                    <?php endif; ?>
                    
                    <!-- NEWS DESK: Visible to Newscasters & Admins -->
                    <?php if ($_SESSION['role'] == 'newscaster' || $_SESSION['role'] == 'admin'): ?>
                    <a href="?page=news_desk" class="flex items-center px-4 py-3 rounded-lg hover:bg-slate-700/50 text-slate-300 hover:text-white transition group <?php echo $page=='news_desk'?'bg-accent/10 text-white border-l-4 border-accent':''; ?>">
                        <i class="fa-solid fa-newspaper w-6 text-slate-400 group-hover:text-blue-400 transition <?php echo $page=='news_desk'?'text-blue-400':''; ?>"></i> 
                        <span class="font-medium text-sm hidden lg:block ml-2 uppercase tracking-wide">News Desk</span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if($_SESSION['role'] == 'admin'): ?>
                    <a href="?page=officers" class="flex items-center px-4 py-3 rounded-lg hover:bg-slate-700/50 text-slate-300 hover:text-white transition group <?php echo $page=='officers'?'bg-accent/10 text-white border-l-4 border-accent':''; ?>">
                        <div class="relative">
                            <i class="fa-solid fa-users-gear w-6 text-slate-400 group-hover:text-blue-400 transition <?php echo $page=='officers'?'text-blue-400':''; ?>"></i>
                            <?php if($pending_count > 0): ?><span class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-red-500 rounded-full animate-pulse border border-slate-900"></span><?php endif; ?>
                        </div>
                        <span class="font-medium text-sm hidden lg:block ml-2 uppercase tracking-wide">Manage Staff</span>
                    </a>
                    <?php endif; ?>
                </nav>

                <div class="p-4 border-t border-slate-700/50 bg-black/20">
                    <a href="?logout=true" class="flex items-center justify-center gap-2 w-full py-2.5 bg-red-500/10 text-red-400 border border-red-500/20 text-center rounded text-xs font-bold uppercase hover:bg-red-500 hover:text-white transition group">
                        <i class="fa-solid fa-power-off group-hover:rotate-90 transition"></i> <span class="hidden lg:inline">Log Out</span>
                    </a>
                </div>
            </aside>

            <!-- Main Content Area -->
            <main class="flex-1 flex flex-col h-full relative overflow-hidden bg-gradient-to-br from-darkbg to-slate-900">
                <div class="map-bg absolute inset-0 opacity-10"></div>
                
                <!-- Header -->
                <header class="h-16 border-b border-slate-700/50 bg-panel/80 backdrop-blur flex items-center justify-between px-8 z-10">
                    <h2 class="font-display text-xl font-bold uppercase text-white tracking-widest flex items-center gap-3">
                        <span class="w-1.5 h-6 bg-accent rounded-full"></span>
                        <?php echo str_replace('_', ' ', $page); ?>
                    </h2>
                    <div class="flex items-center gap-4">
                        <span class="text-xs font-mono text-accent hidden md:inline"><?php echo date('Y-m-d H:i:s'); ?> UTC</span>
                        <a href="index.php" target="_blank" class="text-xs font-bold text-slate-400 hover:text-white transition flex items-center gap-2 px-3 py-1.5 rounded border border-slate-700 hover:border-slate-500">
                            <i class="fa-solid fa-globe"></i> Public View
                        </a>
                    </div>
                </header>

                <div class="flex-1 overflow-y-auto p-8 relative z-10 scroll-smooth">
                    
                    <!-- 1. DASHBOARD -->
                    <?php if ($page == 'dashboard'): 
                        $total = 0; $wanted = 0; $recent = false;
                        try {
                            $total_res = $conn->query("SELECT COUNT(*) as c FROM criminals");
                            if($total_res) $total = $total_res->fetch_assoc()['c'];
                            
                            $wanted_res = $conn->query("SELECT COUNT(*) as c FROM criminals WHERE status='Wanted'");
                            if($wanted_res) $wanted = $wanted_res->fetch_assoc()['c'];

                            $recent = $conn->query("SELECT * FROM criminals ORDER BY created_at DESC LIMIT 5");
                        } catch(Exception $e) {}
                    ?>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                            <div class="glass p-5 rounded-lg border-l-4 border-accent">
                                <p class="text-[10px] uppercase font-bold text-slate-400">Total Records</p>
                                <h3 class="text-3xl font-mono font-bold text-white"><?php echo $total; ?></h3>
                            </div>
                            <div class="glass p-5 rounded-lg border-l-4 border-danger">
                                <p class="text-[10px] uppercase font-bold text-slate-400">Active Warrants</p>
                                <h3 class="text-3xl font-mono font-bold text-white"><?php echo $wanted; ?></h3>
                            </div>
                            <!-- Weather Widget (Mockup) -->
                            <div class="glass p-5 rounded-lg border-l-4 border-yellow-500 flex justify-between items-center">
                                <div>
                                    <p class="text-[10px] uppercase font-bold text-slate-400">Local Weather</p>
                                    <h3 class="text-3xl font-mono font-bold text-white">24°C</h3>
                                </div>
                                <i class="fa-solid fa-cloud-sun text-4xl text-yellow-500"></i>
                            </div>
                            <div class="bg-accent/10 p-5 rounded-lg border border-accent/20 flex flex-col justify-center items-center text-center group cursor-pointer hover:bg-accent/20 transition">
                                <!-- HIDE FROM NEWSCASTER -->
                                <?php if($_SESSION['role'] != 'newscaster'): ?>
                                    <a href="?page=add_record" class="w-full h-full flex flex-col justify-center items-center">
                                        <i class="fa-solid fa-plus text-3xl text-accent mb-2 group-hover:scale-110 transition"></i>
                                        <p class="text-xs font-bold text-white uppercase tracking-widest">Quick Entry</p>
                                    </a>
                                <?php else: ?>
                                    <a href="?page=news_desk" class="w-full h-full flex flex-col justify-center items-center">
                                        <i class="fa-solid fa-newspaper text-3xl text-accent mb-2 group-hover:scale-110 transition"></i>
                                        <p class="text-xs font-bold text-white uppercase tracking-widest">Post News</p>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            <!-- Recent Ops -->
                            <div class="glass rounded-lg overflow-hidden border border-slate-700/50">
                                <div class="p-4 bg-slate-800/50 border-b border-slate-700/50 flex justify-between items-center">
                                    <h3 class="font-display font-bold text-white uppercase tracking-wide">Live Operations Feed</h3>
                                    <span class="w-2 h-2 bg-red-500 rounded-full animate-ping"></span>
                                </div>
                                <div class="p-4 space-y-3">
                                    <?php if($recent && $recent->num_rows > 0): while($r=$recent->fetch_assoc()): ?>
                                    <div class="flex items-center gap-4 p-3 bg-slate-800/50 rounded border border-slate-700/50 hover:border-accent/50 transition">
                                        <img src="<?php echo get_image($r['mugshot']); ?>" class="w-10 h-10 rounded object-cover border border-slate-600">
                                        <div class="flex-1">
                                            <div class="flex justify-between">
                                                <h4 class="text-sm font-bold text-white"><?php echo $r['full_name']; ?></h4>
                                                <span class="text-[10px] font-mono text-slate-500"><?php echo date('H:i', strtotime($r['created_at'])); ?></span>
                                            </div>
                                            <p class="text-xs text-slate-400 uppercase"><?php echo $r['crime_type']; ?> <span class="text-slate-600">|</span> <span class="<?php echo $r['status']=='Wanted'?'text-danger':'text-green-500'; ?> font-bold"><?php echo $r['status']; ?></span></p>
                                        </div>
                                    </div>
                                    <?php endwhile; else: ?>
                                        <p class="text-slate-500 text-xs text-center py-4">No recent activity.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Network Status -->
                            <div class="glass rounded p-6 flex flex-col justify-center items-center text-center">
                                <div class="w-40 h-40 rounded-full border-4 border-slate-700 flex items-center justify-center mb-4 relative">
                                    <div class="absolute inset-0 rounded-full border-t-4 border-accent animate-spin"></div>
                                    <i class="fa-solid fa-server text-5xl text-slate-600"></i>
                                </div>
                                <h3 class="font-display font-bold text-2xl text-white">Network Secure</h3>
                                <p class="text-xs text-slate-500 mt-2 tracking-widest uppercase">Connecting to Interpol Database...<br>Status: Synced</p>
                            </div>
                        </div>

                    <!-- 2. DATABASE GRID (Protected) -->
                    <?php elseif ($page == 'database' && $_SESSION['role'] != 'newscaster'): ?>
                        <div class="glass p-4 rounded-lg mb-6 flex gap-4">
                            <form class="flex-1 flex gap-2" method="GET">
                                <input type="hidden" name="page" value="database">
                                <input type="text" name="q" placeholder="Search criminal records..." class="flex-1 p-3 bg-slate-900 border border-slate-700 rounded text-sm text-white focus:border-accent outline-none">
                                <button class="bg-accent hover:bg-blue-600 text-white px-6 rounded font-bold uppercase text-xs tracking-wider">Search</button>
                            </form>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                            <?php 
                            $q = isset($_GET['q']) ? sanitize($conn, $_GET['q']) : '';
                            $res = $conn->query("SELECT * FROM criminals WHERE full_name LIKE '%$q%' ORDER BY created_at DESC LIMIT 50");
                            if($res && $res->num_rows > 0):
                                while($r = $res->fetch_assoc()): ?>
                                <div class="glass rounded-lg overflow-hidden border border-slate-700 hover:border-accent transition group">
                                    <div class="relative h-48 bg-black">
                                        <img src="<?php echo get_image($r['mugshot']); ?>" class="w-full h-full object-cover opacity-80 group-hover:opacity-100 transition">
                                        <div class="absolute bottom-0 inset-x-0 p-3 bg-gradient-to-t from-black to-transparent">
                                            <h4 class="font-bold text-white truncate"><?php echo $r['full_name']; ?></h4>
                                            <p class="text-xs font-mono uppercase text-accent"><?php echo $r['crime_type']; ?></p>
                                        </div>
                                    </div>
                                    <div class="p-3 grid grid-cols-2 gap-2 text-xs border-t border-slate-700">
                                        <div class="text-slate-400">STATUS: <span class="text-white"><?php echo $r['status']; ?></span></div>
                                        <div class="text-right text-slate-400">RISK: <span class="<?php echo $r['risk_level']=='High'?'text-danger':'text-white'; ?>"><?php echo $r['risk_level']; ?></span></div>
                                        <div class="col-span-2 pt-2 text-center border-t border-slate-700/50 mt-2">
                                            <a href="?page=add_record&edit_id=<?php echo $r['criminal_id']; ?>" class="text-blue-400 hover:text-white font-bold uppercase transition">EDIT FILE <i class="fa-solid fa-arrow-right"></i></a>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile;
                            else: ?>
                                <div class="col-span-full text-center text-slate-500 py-10">No records found.</div>
                            <?php endif; ?>
                        </div>

                    <!-- 3. ADD RECORD (Protected) -->
                    <?php elseif ($page == 'add_record' && $_SESSION['role'] != 'newscaster'): ?>
                        <div class="max-w-4xl mx-auto glass rounded-2xl border border-slate-700 overflow-hidden">
                            <div class="bg-slate-800/50 px-8 py-6 border-b border-slate-700 flex justify-between items-center">
                                <h3 class="font-display font-bold text-lg text-white uppercase tracking-wide">
                                    <i class="fa-solid fa-folder-open text-accent mr-2"></i> <?php echo $edit_data ? 'Edit Case File' : 'New Criminal Record'; ?>
                                </h3>
                                <span class="text-xs font-mono text-slate-500">FORM-IC-99</span>
                            </div>
                            
                            <form method="POST" enctype="multipart/form-data" class="p-8">
                                <?php if($edit_data): ?><input type="hidden" name="edit_id" value="<?php echo $edit_data['criminal_id']; ?>"><?php endif; ?>
                                
                                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                                    <!-- COL 1: PHOTO & RISK -->
                                    <div class="lg:col-span-3 space-y-6">
                                        <div class="w-full aspect-[3/4] bg-slate-900 rounded border-2 border-dashed border-slate-700 flex flex-col items-center justify-center relative overflow-hidden group hover:border-accent transition cursor-pointer">
                                            <?php if($edit_data): ?>
                                                <img src="<?php echo get_image($edit_data['mugshot']); ?>" class="absolute inset-0 w-full h-full object-cover opacity-60 group-hover:opacity-100 transition">
                                            <?php else: ?>
                                                <i class="fa-solid fa-camera text-4xl text-slate-600 mb-2 group-hover:text-accent"></i>
                                                <p class="text-[10px] text-slate-500 uppercase font-bold">Upload Mugshot</p>
                                            <?php endif; ?>
                                            <input type="file" name="c_photo" class="absolute inset-0 opacity-0 cursor-pointer">
                                        </div>
                                        
                                        <div class="bg-slate-800/50 p-4 rounded border border-slate-700">
                                            <label class="text-[10px] font-bold uppercase text-slate-500 mb-1 block">Threat Assessment</label>
                                            <select name="c_risk" class="w-full p-2 input-pro rounded text-sm mb-3">
                                                <option class="text-green-400">Low</option>
                                                <option class="text-yellow-400">Medium</option>
                                                <option class="text-red-500" selected>High</option>
                                            </select>
                                            <label class="text-[10px] font-bold uppercase text-slate-500 mb-1 block">Current Status</label>
                                            <select name="c_status" class="w-full p-2 input-pro rounded text-sm font-bold">
                                                <option value="Wanted">Wanted</option>
                                                <option value="In Custody">In Custody</option>
                                                <option value="Solved">Closed</option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- COL 2: PERSONAL DATA -->
                                    <div class="lg:col-span-5 space-y-4">
                                        <h4 class="text-xs font-bold text-accent uppercase border-b border-slate-700 pb-2 mb-4">Personal Information</h4>
                                        <div class="grid grid-cols-2 gap-4">
                                            <div class="col-span-2">
                                                <label class="text-[10px] font-bold uppercase text-slate-500">Full Name</label>
                                                <input type="text" name="c_name" value="<?php echo $edit_data['full_name']??''; ?>" class="w-full p-2 input-pro rounded" required>
                                            </div>
                                            <div>
                                                <label class="text-[10px] font-bold uppercase text-slate-500">Alias</label>
                                                <input type="text" name="c_alias" value="<?php echo $edit_data['alias']??''; ?>" class="w-full p-2 input-pro rounded">
                                            </div>
                                            <div>
                                                <label class="text-[10px] font-bold uppercase text-slate-500">Gang/Affiliation</label>
                                                <input type="text" name="c_gang" value="<?php echo $edit_data['gang_affiliation']??''; ?>" class="w-full p-2 input-pro rounded">
                                            </div>
                                            <div>
                                                <label class="text-[10px] font-bold uppercase text-slate-500">Age</label>
                                                <input type="number" name="c_age" value="<?php echo $edit_data['age']??''; ?>" class="w-full p-2 input-pro rounded" required>
                                            </div>
                                            <div>
                                                <label class="text-[10px] font-bold uppercase text-slate-500">Gender</label>
                                                <select name="c_gender" class="w-full p-2 input-pro rounded"><option>Male</option><option>Female</option></select>
                                            </div>
                                            <div>
                                                <label class="text-[10px] font-bold uppercase text-slate-500">Nationality</label>
                                                <input type="text" name="c_nat" value="<?php echo $edit_data['nationality']??''; ?>" class="w-full p-2 input-pro rounded">
                                            </div>
                                        </div>
                                        
                                        <h4 class="text-xs font-bold text-accent uppercase border-b border-slate-700 pb-2 mb-4 mt-6">Physical Description</h4>
                                        <div class="grid grid-cols-2 gap-4">
                                            <div><input type="text" name="c_height" placeholder="Height" class="w-full p-2 input-pro rounded text-xs" value="<?php echo $edit_data['height']??''; ?>"></div>
                                            <div><input type="text" name="c_weight" placeholder="Weight" class="w-full p-2 input-pro rounded text-xs" value="<?php echo $edit_data['weight']??''; ?>"></div>
                                            <div><input type="text" name="c_eyes" placeholder="Eye Color" class="w-full p-2 input-pro rounded text-xs" value="<?php echo $edit_data['eye_color']??''; ?>"></div>
                                            <div><input type="text" name="c_hair" placeholder="Hair Color" class="w-full p-2 input-pro rounded text-xs" value="<?php echo $edit_data['hair_color']??''; ?>"></div>
                                            <div class="col-span-2"><input type="text" name="c_scars" placeholder="Scars/Tattoos/Marks" class="w-full p-2 input-pro rounded text-xs" value="<?php echo $edit_data['scars_marks']??''; ?>"></div>
                                        </div>
                                    </div>

                                    <!-- COL 3: CRIME DATA -->
                                    <div class="lg:col-span-4 space-y-4">
                                        <h4 class="text-xs font-bold text-accent uppercase border-b border-slate-700 pb-2 mb-4">Case Details</h4>
                                        <div>
                                            <label class="text-[10px] font-bold uppercase text-slate-500">Crime Category</label>
                                            <select name="c_type" class="w-full p-2 input-pro rounded">
                                                <option>Theft</option><option>Homicide</option><option>Cyber Crime</option><option>Drug Trafficking</option><option>Assault</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="text-[10px] font-bold uppercase text-slate-500">Fingerprint ID</label>
                                            <input type="text" name="c_fp" value="<?php echo $edit_data['fingerprint_id']??''; ?>" class="w-full p-2 input-pro rounded font-mono text-xs" placeholder="FP-SHA-256">
                                        </div>
                                        <div>
                                            <label class="text-[10px] font-bold uppercase text-slate-500">Bail Status</label>
                                            <input type="text" name="c_bail" value="<?php echo $edit_data['bail_status']??''; ?>" class="w-full p-2 input-pro rounded text-xs">
                                        </div>
                                        <div>
                                            <label class="text-[10px] font-bold uppercase text-slate-500">Evidence List</label>
                                            <textarea name="c_evid" rows="2" class="w-full p-2 input-pro rounded text-xs resize-none" placeholder="Items..."><?php echo $edit_data['evidence_list']??''; ?></textarea>
                                        </div>
                                        <div>
                                            <label class="text-[10px] font-bold uppercase text-slate-500">Incident Report</label>
                                            <textarea name="c_desc" rows="4" class="w-full p-3 input-pro rounded text-sm resize-none" required><?php echo $edit_data['description']??''; ?></textarea>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="pt-6 border-t border-slate-700 flex justify-end gap-4">
                                    <button type="submit" name="save_criminal" class="bg-accent hover:bg-blue-600 text-white px-10 py-3 rounded font-bold uppercase text-xs tracking-widest transition shadow-glow">
                                        <i class="fa-solid fa-save mr-2"></i> Save Record
                                    </button>
                                </div>
                            </form>
                        </div>

                    <!-- 4. NEWS DESK (NEW FEATURE) -->
                    <?php elseif ($page == 'news_desk' && ($_SESSION['role'] == 'newscaster' || $_SESSION['role'] == 'admin')): ?>
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                            <div class="glass rounded-xl p-6 border border-slate-700">
                                <h3 class="font-bold text-lg text-white mb-4 border-b border-slate-700 pb-2">Publish News</h3>
                                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                                    <div><label class="text-xs font-bold text-slate-500 uppercase">Headline</label><input type="text" name="n_title" class="w-full p-2 input-pro rounded" required></div>
                                    <div><label class="text-xs font-bold text-slate-500 uppercase">Type</label>
                                        <select name="n_type" class="w-full p-2 input-pro rounded">
                                            <option value="News">General News</option>
                                            <option value="Alert" class="text-red-500">Emergency Alert</option>
                                            <option value="Notice">Public Notice</option>
                                        </select>
                                    </div>
                                    <div><label class="text-xs font-bold text-slate-500 uppercase">Content</label><textarea name="n_content" rows="6" class="w-full p-2 input-pro rounded resize-none" required></textarea></div>
                                    <div><label class="text-xs font-bold text-slate-500 uppercase">Media</label><input type="file" name="n_media" class="w-full text-xs input-pro p-2 rounded"></div>
                                    <button type="submit" name="save_news" class="w-full bg-accent hover:bg-blue-600 text-white font-bold py-2 rounded uppercase text-xs">Publish</button>
                                </form>
                            </div>
                            <div class="lg:col-span-2 glass rounded-xl p-6 border border-slate-700">
                                <h3 class="font-bold text-lg text-white mb-4">Recent Posts</h3>
                                <?php $news = $conn->query("SELECT * FROM news_feed ORDER BY created_at DESC LIMIT 5");
                                if ($news && $news->num_rows > 0): while($n=$news->fetch_assoc()): ?>
                                <div class="flex gap-4 p-4 border-b border-slate-700/50 last:border-0">
                                    <?php if($n['media']): ?><img src="uploads/<?php echo $n['media']; ?>" class="w-20 h-20 object-cover rounded"><?php endif; ?>
                                    <div>
                                        <span class="text-[10px] font-bold px-2 py-1 bg-slate-800 rounded uppercase <?php echo $n['type']=='Alert'?'text-red-500':'text-blue-400'; ?>"><?php echo $n['type']; ?></span>
                                        <h4 class="font-bold text-white mt-1"><?php echo $n['title']; ?></h4>
                                        <p class="text-xs text-slate-400 mt-1 line-clamp-2"><?php echo $n['content']; ?></p>
                                        <p class="text-[10px] text-slate-600 mt-2"><?php echo date('M d, H:i', strtotime($n['created_at'])); ?></p>
                                    </div>
                                </div>
                                <?php endwhile; else: echo "<p class='text-slate-500 text-xs text-center py-4'>No news published yet.</p>"; endif; ?>
                            </div>
                        </div>
                    
                    <?php elseif($page != 'dashboard' && $_SESSION['role'] == 'newscaster'): ?>
                        <!-- ACCESS DENIED FOR NEWSCASTERS TRYING TO ACCESS RESTRICTED PAGES -->
                        <div class="flex h-full items-center justify-center">
                            <div class="text-center glass p-12 rounded-xl">
                                <i class="fa-solid fa-lock text-6xl text-red-500 mb-4"></i>
                                <h2 class="text-2xl font-bold text-white">ACCESS RESTRICTED</h2>
                                <p class="text-slate-500 mt-2 uppercase tracking-widest text-xs">Security Clearance Insufficient</p>
                            </div>
                        </div>

                    <!-- 5. MANAGE OFFICERS (ADMIN ONLY) -->
                    <?php elseif ($page == 'officers' && $_SESSION['role'] == 'admin'): ?>
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            <!-- Pending Applications -->
                            <div class="glass rounded-xl shadow-lg border border-slate-700/50 overflow-hidden">
                                <div class="p-4 bg-slate-800/80 border-b border-slate-700 flex justify-between items-center">
                                    <h3 class="font-bold text-white uppercase text-sm flex items-center gap-2">
                                        <i class="fa-solid fa-clock text-yellow-500"></i> Pending Approvals
                                    </h3>
                                    <span class="bg-yellow-500/20 text-yellow-500 text-xs font-bold px-2 py-1 rounded"><?php echo $pending_count; ?> New</span>
                                </div>
                                <div class="p-4 space-y-3">
                                    <?php 
                                    try {
                                        $pending = $conn->query("SELECT * FROM users WHERE status='pending'");
                                        if($pending && $pending->num_rows > 0):
                                            while($u = $pending->fetch_assoc()): ?>
                                            <div class="flex items-center justify-between p-3 bg-slate-900/50 rounded border border-slate-700">
                                                <div>
                                                    <p class="text-white font-bold text-sm"><?php echo $u['full_name']; ?></p>
                                                    <p class="text-xs text-slate-500"><?php echo $u['username']; ?> | <?php echo strtoupper($u['role']); ?></p>
                                                </div>
                                                <div class="flex gap-2">
                                                    <a href="?approve_id=<?php echo $u['user_id']; ?>" class="bg-green-600 text-white px-3 py-1 rounded text-xs font-bold hover:bg-green-500 transition">Approve</a>
                                                    <a href="?reject_id=<?php echo $u['user_id']; ?>" onclick="return confirm('Reject application?')" class="bg-red-600 text-white px-3 py-1 rounded text-xs font-bold hover:bg-red-500 transition">Reject</a>
                                                </div>
                                            </div>
                                        <?php endwhile; else: ?>
                                            <p class="text-center text-slate-500 text-xs py-4">No pending applications.</p>
                                        <?php endif;
                                    } catch (Exception $e) { echo "<p>Error loading pending list.</p>"; } ?>
                                </div>
                            </div>

                            <!-- Active Roster -->
                            <div class="glass rounded-xl shadow-lg border border-slate-700/50 overflow-hidden">
                                <div class="p-4 bg-slate-800/80 border-b border-slate-700">
                                    <h3 class="font-bold text-white uppercase text-sm flex items-center gap-2">
                                        <i class="fa-solid fa-users text-blue-500"></i> Active Personnel
                                    </h3>
                                </div>
                                <div class="max-h-96 overflow-y-auto p-4 space-y-2">
                                    <?php 
                                    try {
                                        $active = $conn->query("SELECT * FROM users WHERE status='active' ORDER BY unit");
                                        while($u = $active->fetch_assoc()): ?>
                                            <div class="flex items-center justify-between p-2 hover:bg-slate-800/30 rounded transition">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-8 h-8 rounded bg-slate-700 flex items-center justify-center text-xs font-bold text-white">
                                                        <?php echo substr($u['full_name'],0,1); ?>
                                                    </div>
                                                    <div>
                                                        <p class="text-slate-300 text-xs font-bold"><?php echo $u['full_name']; ?></p>
                                                        <p class="text-[10px] text-slate-500 uppercase"><?php echo $u['unit']; ?> Unit</p>
                                                    </div>
                                                </div>
                                                <?php if($u['role'] != 'admin'): ?>
                                                    <a href="?suspend_id=<?php echo $u['user_id']; ?>" onclick="return confirm('Suspend this officer?')" class="text-red-500 hover:text-red-400 text-xs font-bold uppercase">Suspend</a>
                                                <?php else: ?>
                                                    <span class="text-blue-500 text-[10px] font-bold uppercase">Admin</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endwhile;
                                    } catch (Exception $e) {} ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    <?php endif; ?>

    <script>
        function openSolveModal(id) {
            document.getElementById('modalCaseId').value = id;
            document.getElementById('solveModal').classList.remove('hidden');
        }
    </script>
</body>
</html>