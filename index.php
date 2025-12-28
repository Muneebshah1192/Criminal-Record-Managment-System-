<?php
/**
 * NCB PUBLIC PORTAL (v15.3 - PREMIUM ENHANCED)
 * -------------------------------------------
 * New Features:
 * - Optimized Case Status Overview (Compact & Professional)
 * - Fixed News Display from Database
 * - Enhanced Visual Design
 */

session_start();
// Error handling for production
error_reporting(0);
ini_set('display_errors', 0);

// Database Connection with enhanced error handling
$db_online = false;
$total_criminals = 0;
$graph_data = [
    'wanted' => 0, 
    'solved' => 0, 
    'custody' => 0,
    'high_risk' => 0,
    'gang_activity' => 0
];

$crime_distribution = [];
$criminal_nationalities = [];
$custom_news = [];
$breaking_news = [];

try {
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = @new mysqli("localhost", "root", "", "crms_db");
    
    if ($conn && !$conn->connect_error) {
        $db_online = true;
        
        // Fetch comprehensive stats
        $w = $conn->query("SELECT COUNT(*) as c FROM criminals WHERE status='Wanted'");
        if($w) $graph_data['wanted'] = $w->fetch_assoc()['c'];
        
        $s = $conn->query("SELECT COUNT(*) as c FROM criminals WHERE status='Solved'");
        if($s) $graph_data['solved'] = $s->fetch_assoc()['c'];
        
        $c = $conn->query("SELECT COUNT(*) as c FROM criminals WHERE status='In Custody'");
        if($c) $graph_data['custody'] = $c->fetch_assoc()['c'];
        
        $hr = $conn->query("SELECT COUNT(*) as c FROM criminals WHERE risk_level='High'");
        if($hr) $graph_data['high_risk'] = $hr->fetch_assoc()['c'];
        
        $ga = $conn->query("SELECT COUNT(*) as c FROM criminals WHERE gang_affiliation != ''");
        if($ga) $graph_data['gang_activity'] = $ga->fetch_assoc()['c'];
        
        // Total criminals
        $total_res = $conn->query("SELECT COUNT(*) as c FROM criminals");
        if($total_res) $total_criminals = $total_res->fetch_assoc()['c'];
        
        // Crime type distribution (for heatmap)
        $crime_types = $conn->query("SELECT crime_type, COUNT(*) as count FROM criminals GROUP BY crime_type ORDER BY count DESC LIMIT 10");
        while($row = $crime_types->fetch_assoc()) {
            $crime_distribution[] = $row;
        }
        
        // Nationality distribution
        $nationalities = $conn->query("SELECT nationality, COUNT(*) as count FROM criminals WHERE nationality != '' GROUP BY nationality ORDER BY count DESC LIMIT 8");
        while($row = $nationalities->fetch_assoc()) {
            $criminal_nationalities[] = $row;
        }
        
        // Fetch custom news from database - Check if news table exists
        $check_news_table = $conn->query("SHOW TABLES LIKE 'news'");
        if($check_news_table && $check_news_table->num_rows > 0) {
            // Get breaking news (priority 1)
            $breaking_news_result = $conn->query("SELECT * FROM news WHERE priority = 1 AND status = 'published' ORDER BY created_at DESC LIMIT 1");
            if($breaking_news_result && $breaking_news_result->num_rows > 0) {
                $breaking_news = $breaking_news_result->fetch_assoc();
            }
            
            // Get recent custom news
            $custom_news_result = $conn->query("SELECT * FROM news WHERE status = 'published' ORDER BY priority DESC, created_at DESC LIMIT 4");
            if($custom_news_result) {
                while($row = $custom_news_result->fetch_assoc()) {
                    $custom_news[] = $row;
                }
            }
        } else {
            // News table doesn't exist - create it
            $conn->query("CREATE TABLE IF NOT EXISTS news (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                content TEXT NOT NULL,
                category VARCHAR(50) DEFAULT 'General',
                image_url VARCHAR(500),
                source VARCHAR(100),
                priority INT DEFAULT 3 COMMENT '1=Breaking, 2=Important, 3=Regular',
                status ENUM('draft', 'published') DEFAULT 'published',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )");
            
            // Insert sample news if table was just created
            $conn->query("INSERT INTO news (title, content, category, priority, status) VALUES 
                ('Major Drug Bust Operation Success', 'NCB teams conducted successful raids across multiple cities resulting in significant narcotics seizure.', 'Security Updates', 1, 'published'),
                ('New Cyber Crime Unit Established', 'National Crime Bureau launches specialized division to combat online fraud and digital crimes.', 'Announcements', 2, 'published'),
                ('Community Safety Program Launched', 'New initiative to enhance public safety through neighborhood watch programs.', 'Community', 3, 'published')
            ");
            
            // Now fetch the news again
            $breaking_news_result = $conn->query("SELECT * FROM news WHERE priority = 1 AND status = 'published' ORDER BY created_at DESC LIMIT 1");
            if($breaking_news_result && $breaking_news_result->num_rows > 0) {
                $breaking_news = $breaking_news_result->fetch_assoc();
            }
            
            $custom_news_result = $conn->query("SELECT * FROM news WHERE status = 'published' ORDER BY priority DESC, created_at DESC LIMIT 4");
            if($custom_news_result) {
                while($row = $custom_news_result->fetch_assoc()) {
                    $custom_news[] = $row;
                }
            }
        }
    }
} catch (Exception $e) {
    $db_online = false;
}

// Helper: Smart Image Loader with caching
function get_criminal_image($img, $name) {
    $target_file = "uploads/" . $img;
    if ($img && $img !== 'default.png' && file_exists($target_file)) {
        return $target_file . "?" . filemtime($target_file);
    }
    // Fallback to dynamic avatar
    $name_encoded = urlencode(str_replace(' ', '+', $name));
    return "https://api.dicebear.com/7.x/avataaars/svg?seed=" . md5($name) . "&background=%230f172a&radius=50&size=256";
}

// Get recent arrests for news feed
$recent_arrests = [];
if ($db_online) {
    $arrests = $conn->query("SELECT * FROM criminals WHERE status='In Custody' ORDER BY created_at DESC LIMIT 5");
    while($row = $arrests->fetch_assoc()) {
        $recent_arrests[] = $row;
    }
}

// Function to format news date
function format_news_date($date) {
    if (!$date) return 'Recently';
    
    $timestamp = strtotime($date);
    $now = time();
    $diff = $now - $timestamp;
    
    if ($diff < 3600) {
        return floor($diff / 60) . ' minutes ago';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' hours ago';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . ' days ago';
    } else {
        return date('M d, Y', $timestamp);
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth dark" id="html-theme">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NCB Portal | National Crime Bureau - Public Intelligence Dashboard</title>
    <meta name="description" content="Official public portal of the National Crime Bureau. Access crime statistics, wanted lists, and report criminal activities securely.">
    
    <!-- TAILWIND CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- CHART.JS & MAP LIBRARIES -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <!-- FONTS & ICONS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700;800&family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        display: ['Rajdhani', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    },
                    colors: {
                        ncb: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                            950: '#082f49',
                        },
                        police: { 
                            50: '#f8fafc',
                            100: '#f1f5f9',
                            200: '#e2e8f0',
                            300: '#cbd5e1',
                            400: '#94a3b8',
                            500: '#64748b',
                            600: '#475569',
                            700: '#334155',
                            800: '#1e293b',
                            900: '#0f172a',
                            950: '#020617',
                        },
                        accent: '#3b82f6',
                        danger: '#ef4444',
                        success: '#10b981',
                        warning: '#f59e0b',
                    },
                    animation: {
                        'scan': 'scan 3s linear infinite',
                        'fade-in-up': 'fadeInUp 0.8s ease-out forwards',
                        'pulse-glow': 'pulseGlow 2s ease-in-out infinite',
                        'float': 'float 6s ease-in-out infinite',
                        'shimmer': 'shimmer 2s linear infinite',
                        'ticker': 'ticker 30s linear infinite',
                    },
                    keyframes: {
                        scan: {
                            '0%': { top: '0%' },
                            '50%': { top: '100%' },
                            '100%': { top: '0%' },
                        },
                        fadeInUp: {
                            '0%': { opacity: '0', transform: 'translateY(20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        },
                        pulseGlow: {
                            '0%, 100%': { boxShadow: '0 0 20px rgba(59, 130, 246, 0.3)' },
                            '50%': { boxShadow: '0 0 40px rgba(59, 130, 246, 0.6)' },
                        },
                        float: {
                            '0%, 100%': { transform: 'translateY(0px)' },
                            '50%': { transform: 'translateY(-20px)' },
                        },
                        shimmer: {
                            '0%': { backgroundPosition: '-1000px 0' },
                            '100%': { backgroundPosition: '1000px 0' },
                        },
                        ticker: {
                            '0%': { transform: 'translateX(100%)' },
                            '100%': { transform: 'translateX(-100%)' },
                        }
                    }
                }
            }
        }
    </script>
    
    <style>
        :root {
            --ncb-primary: #0ea5e9;
            --ncb-dark: #0f172a;
        }
        
        .hero-gradient {
            background: linear-gradient(135deg, 
                rgba(15, 23, 42, 0.95) 0%, 
                rgba(30, 41, 59, 0.9) 30%, 
                rgba(15, 23, 42, 0.85) 70%, 
                rgba(2, 132, 199, 0.2) 100%);
        }
        
        .glass-panel {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        
        .text-gradient {
            background: linear-gradient(135deg, #0ea5e9, #3b82f6, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .crime-card-hover {
            transition: all 0.3s ease;
        }
        
        .crime-card-hover:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        
        .map-tooltip {
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 8px;
            padding: 12px;
            color: white;
            font-size: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }
        
        /* Custom scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 8px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-track {
            background: rgba(30, 41, 59, 0.5);
            border-radius: 4px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(59, 130, 246, 0.6);
            border-radius: 4px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(59, 130, 246, 0.8);
        }
        
        /* Shimmer loading effect */
        .shimmer-bg {
            background: linear-gradient(90deg, 
                rgba(255, 255, 255, 0.1) 25%, 
                rgba(255, 255, 255, 0.2) 37%, 
                rgba(255, 255, 255, 0.1) 63%);
            background-size: 1000px 100%;
        }
        
        /* Compact chart styles */
        .compact-chart-container {
            position: relative;
            width: 140px;
            height: 140px;
        }
        
        .chart-center-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
        }
        
        /* News ticker */
        .news-ticker-container {
            overflow: hidden;
            white-space: nowrap;
            position: relative;
        }
        
        .news-ticker-content {
            display: inline-block;
            padding-left: 100%;
            animation: ticker 30s linear infinite;
        }
        
        .news-ticker-content:hover {
            animation-play-state: paused;
        }
        
        /* Professional card hover effects */
        .professional-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid transparent;
        }
        
        .professional-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.25);
            border-color: rgba(59, 130, 246, 0.2);
        }
        
        /* Elegant gradient borders */
        .gradient-border {
            position: relative;
            border: 1px solid transparent;
            background: linear-gradient(white, white) padding-box,
                        linear-gradient(135deg, #0ea5e9, #3b82f6) border-box;
        }
        
        .dark .gradient-border {
            background: linear-gradient(#0f172a, #0f172a) padding-box,
                        linear-gradient(135deg, #0ea5e9, #3b82f6) border-box;
        }
    </style>
</head>
<body class="bg-white dark:bg-police-950 text-police-900 dark:text-slate-200 font-sans antialiased overflow-x-hidden transition-colors duration-300">

    <!-- ANNOUNCEMENT BAR -->
    <div class="bg-gradient-to-r from-danger/90 to-danger/70 text-white py-2 px-4 text-center text-sm font-bold uppercase tracking-widest flex items-center justify-center gap-3">
        <i class="fa-solid fa-triangle-exclamation animate-pulse"></i>
        <span>EMERGENCY HOTLINE: <strong>911</strong> | Anonymous Tips: <strong>1-800-CRIME-REPORT</strong></span>
        <i class="fa-solid fa-triangle-exclamation animate-pulse"></i>
    </div>

    <!-- NAVIGATION BAR -->
    <nav class="sticky top-0 w-full z-50 transition-all duration-300 bg-white/80 dark:bg-police-900/90 backdrop-blur-md border-b border-slate-200 dark:border-police-800 shadow-lg" id="navbar">
        <div class="container mx-auto px-6 py-3">
            <div class="flex justify-between items-center">
                
                <!-- Logo Area -->
                <a href="index.php" class="flex items-center gap-3 group" aria-label="NCB Portal Home">
                    <div class="bg-gradient-to-br from-ncb-600 to-ncb-800 text-white w-12 h-12 rounded-xl flex items-center justify-center shadow-lg shadow-ncb-500/30 group-hover:scale-105 transition-transform duration-300">
                        <i class="fa-solid fa-shield-halved text-xl"></i>
                    </div>
                    <div class="leading-none">
                        <h1 class="font-display font-bold text-2xl dark:text-white text-police-900 uppercase tracking-tight">NCB<span class="text-ncb-500">.</span>Portal</h1>
                        <p class="text-[10px] text-slate-500 dark:text-slate-400 font-bold tracking-[0.2em] uppercase">National Crime Bureau</p>
                    </div>
                </a>

                <!-- Center Navigation -->
                <div class="hidden lg:flex items-center gap-6 text-sm font-medium tracking-wide dark:text-slate-300">
                    <a href="#home" class="hover:text-ncb-500 dark:hover:text-ncb-400 transition px-3 py-2 rounded-lg hover:bg-white/10" aria-current="page">Home</a>
                    <a href="#wanted" class="hover:text-danger dark:hover:text-red-400 transition px-3 py-2 rounded-lg hover:bg-white/10">Most Wanted</a>
                    <a href="#analytics" class="hover:text-ncb-500 dark:hover:text-ncb-400 transition px-3 py-2 rounded-lg hover:bg-white/10">Analytics</a>
                    <a href="#heatmap" class="hover:text-ncb-500 dark:hover:text-ncb-400 transition px-3 py-2 rounded-lg hover:bg-white/10">Crime Map</a>
                    <a href="#news" class="hover:text-ncb-500 dark:hover:text-ncb-400 transition px-3 py-2 rounded-lg hover:bg-white/10">News</a>
                    <a href="#resources" class="hover:text-ncb-500 dark:hover:text-ncb-400 transition px-3 py-2 rounded-lg hover:bg-white/10">Resources</a>
                </div>

                <!-- Right Actions -->
                <div class="flex items-center gap-4">
                    <!-- Theme Toggle -->
                    <button id="theme-toggle" class="w-10 h-10 rounded-full bg-slate-100 dark:bg-police-800 flex items-center justify-center hover:bg-slate-200 dark:hover:bg-police-700 transition" aria-label="Toggle dark/light mode">
                        <i class="fa-solid fa-moon text-slate-700 dark:text-yellow-300" id="theme-icon"></i>
                    </button>
                    
                    <!-- Search Button -->
                    <button onclick="openSearch()" class="w-10 h-10 rounded-full bg-slate-100 dark:bg-police-800 flex items-center justify-center hover:bg-slate-200 dark:hover:bg-police-700 transition" aria-label="Open search">
                        <i class="fa-solid fa-search text-slate-700 dark:text-slate-300"></i>
                    </button>
                    
                    <!-- Login Button -->
                    <a href="login.php" class="flex items-center gap-2 bg-ncb-600 hover:bg-ncb-700 text-white border border-ncb-500 px-5 py-2.5 rounded-full text-xs font-bold uppercase tracking-wider transition-all duration-300 transform hover:-translate-y-0.5 hover:shadow-lg hover:shadow-ncb-500/30">
                        <i class="fa-solid fa-lock"></i> Officer Login
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- HERO SECTION WITH CUSTOM NEWS -->
    <section id="home" class="relative overflow-hidden min-h-[85vh] flex items-center hero-gradient pt-16">
        <!-- Animated Background Elements -->
        <div class="absolute inset-0">
            <div class="absolute top-1/4 left-1/4 w-64 h-64 bg-ncb-500/10 rounded-full blur-3xl"></div>
            <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-danger/5 rounded-full blur-3xl"></div>
        </div>
        
        <!-- Data Grid Background -->
        <div class="absolute inset-0 opacity-[0.02] bg-[length:40px_40px] bg-[linear-gradient(to_right,#0ea5e9_1px,transparent_1px),linear-gradient(to_bottom,#0ea5e9_1px,transparent_1px)]"></div>

        <div class="container mx-auto px-6 relative z-10">
            <!-- Breaking News Ticker (if available) -->
            <?php if (!empty($breaking_news)): ?>
            <div class="mb-6">
                <div class="bg-gradient-to-r from-danger/80 to-red-700/80 text-white p-4 rounded-xl border border-red-500/30 shadow-lg">
                    <div class="flex items-center gap-3 mb-2">
                        <i class="fa-solid fa-bolt text-yellow-300 animate-pulse"></i>
                        <h3 class="font-display font-bold text-lg">BREAKING NEWS</h3>
                        <span class="ml-auto text-xs bg-red-800 px-3 py-1 rounded-full font-bold">LIVE</span>
                    </div>
                    <p class="text-white font-medium"><?php echo htmlspecialchars($breaking_news['title']); ?></p>
                    <div class="flex items-center justify-between mt-3">
                        <span class="text-xs text-red-200"><?php echo format_news_date($breaking_news['created_at']); ?></span>
                        <?php if (!empty($breaking_news['source'])): ?>
                        <span class="text-xs text-red-200">Source: <?php echo htmlspecialchars($breaking_news['source']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 items-center">
                
                <!-- Hero Content -->
                <div class="text-white space-y-7 animate-fade-in-up">
                    <div class="inline-flex items-center gap-3 bg-gradient-to-r from-ncb-600/20 to-danger/20 border border-ncb-500/30 text-ncb-300 px-5 py-2.5 rounded-full text-xs font-bold uppercase tracking-widest shadow-lg">
                        <span class="w-2 h-2 bg-ncb-400 rounded-full animate-pulse"></span> 
                        <span class="text-white">Real-time Intelligence Dashboard</span>
                        <span class="w-2 h-2 bg-danger rounded-full animate-pulse"></span>
                    </div>
                    
                    <h1 class="font-display text-4xl md:text-6xl font-bold leading-tight">
                        <span class="block">NATIONAL</span>
                        <span class="block text-gradient">CRIME BUREAU</span>
                        <span class="block text-lg md:text-xl font-normal text-slate-300 mt-3">Public Intelligence Portal</span>
                    </h1>
                    
                    <p class="text-slate-300 max-w-2xl leading-relaxed border-l-3 border-ncb-500 pl-5">
                        Access comprehensive crime statistics, track active investigations, and contribute to community safety through our secure reporting system.
                    </p>
                    
                    <!-- CTA Buttons -->
                    <div class="flex flex-wrap gap-3 pt-4">
                        <a href="#wanted" class="bg-gradient-to-r from-danger to-red-700 hover:from-red-700 hover:to-red-800 text-white px-7 py-3.5 rounded-xl font-bold uppercase tracking-wider transition-all duration-300 shadow-xl shadow-danger/30 hover:shadow-2xl hover:shadow-danger/50 flex items-center gap-3 group">
                            <i class="fa-solid fa-crosshairs"></i> 
                            <span>Most Wanted</span>
                            <i class="fa-solid fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                        </a>
                        <a href="#analytics" class="glass-panel hover:bg-white/10 text-white px-7 py-3.5 rounded-xl font-bold uppercase tracking-wider transition-all duration-300 flex items-center gap-3 group border border-white/20">
                            <i class="fa-solid fa-chart-simple"></i>
                            <span>View Analytics</span>
                        </a>
                    </div>
                </div>

                <!-- Hero Visualization with News Feed -->
                <div class="relative">
                    <div class="relative w-full bg-gradient-to-br from-ncb-900/30 to-police-900/30 rounded-2xl border border-ncb-500/20 backdrop-blur-sm p-6">
                        <div class="flex justify-between items-start mb-5">
                            <div>
                                <h3 class="font-display text-lg font-bold text-white">Live Crime Tracker</h3>
                                <p class="text-slate-400 text-sm">Updated in real-time</p>
                            </div>
                            <div class="text-right">
                                <div class="text-xl font-mono font-bold text-ncb-400"><?php echo date('H:i'); ?></div>
                                <div class="text-xs text-slate-500 uppercase font-bold">Local Time</div>
                            </div>
                        </div>
                        
                        <!-- Compact Case Status -->
                        <div class="mb-5">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="text-slate-300 font-bold text-sm">Case Status Overview</h4>
                                <span class="text-xs text-slate-500"><?php echo date('M d'); ?></span>
                            </div>
                            <div class="flex items-center justify-center gap-6">
                                <!-- Compact Chart -->
                                <div class="compact-chart-container">
                                    <canvas id="heroCompactChart" width="140" height="140"></canvas>
                                    <div class="chart-center-text">
                                        <div class="text-2xl font-display font-bold text-white"><?php echo $total_criminals; ?></div>
                                        <div class="text-xs text-slate-400 uppercase">Total</div>
                                    </div>
                                </div>
                                
                                <!-- Quick Stats -->
                                <div class="space-y-2">
                                    <div class="flex items-center gap-3">
                                        <div class="w-2.5 h-2.5 rounded-full bg-danger"></div>
                                        <div>
                                            <div class="text-white font-bold text-lg"><?php echo $graph_data['wanted']; ?></div>
                                            <div class="text-xs text-slate-400">Wanted</div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="w-2.5 h-2.5 rounded-full bg-success"></div>
                                        <div>
                                            <div class="text-white font-bold text-lg"><?php echo $graph_data['solved']; ?></div>
                                            <div class="text-xs text-slate-400">Solved</div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="w-2.5 h-2.5 rounded-full bg-accent"></div>
                                        <div>
                                            <div class="text-white font-bold text-lg"><?php echo $graph_data['custody']; ?></div>
                                            <div class="text-xs text-slate-400">Custody</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- NCB News Updates -->
                        <div class="bg-police-800/50 p-4 rounded-xl">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-2">
                                    <i class="fa-solid fa-newspaper text-ncb-400 text-sm"></i>
                                    <h4 class="font-display font-bold text-white text-sm">NCB News Updates</h4>
                                </div>
                                <span class="text-xs text-slate-500">Official</span>
                            </div>
                            
                            <div class="space-y-2">
                                <?php if (!empty($custom_news)): ?>
                                    <?php foreach(array_slice($custom_news, 0, 2) as $news): ?>
                                    <div class="flex items-start gap-3 p-2.5 bg-police-900/30 rounded-lg hover:bg-police-900/50 transition cursor-pointer" onclick="openNewsModal(<?php echo $news['id']; ?>)">
                                        <div class="w-1.5 h-1.5 mt-2 rounded-full <?php echo $news['priority'] == 1 ? 'bg-danger animate-pulse' : ($news['priority'] == 2 ? 'bg-warning' : 'bg-ncb-400'); ?>"></div>
                                        <div class="flex-1 min-w-0">
                                            <h5 class="text-sm font-medium text-white truncate"><?php echo htmlspecialchars($news['title']); ?></h5>
                                            <div class="flex items-center justify-between mt-1">
                                                <span class="text-xs text-slate-400 truncate"><?php echo format_news_date($news['created_at']); ?></span>
                                                <span class="text-xs px-1.5 py-0.5 rounded bg-ncb-900/50 text-ncb-300 truncate ml-2"><?php echo htmlspecialchars($news['category'] ?? 'General'); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center py-3">
                                        <i class="fa-solid fa-newspaper text-xl text-slate-600 mb-1"></i>
                                        <p class="text-xs text-slate-400">No official news updates available</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- COMPACT & PROFESSIONAL STATISTICS DASHBOARD -->
    <section id="analytics" class="py-14 bg-gradient-to-b from-white to-slate-50 dark:from-police-950 dark:to-police-900">
        <div class="container mx-auto px-6">
            <div class="text-center max-w-3xl mx-auto mb-10">
                <div class="inline-flex items-center gap-2 bg-ncb-50 dark:bg-ncb-900/30 text-ncb-600 dark:text-ncb-400 px-4 py-2 rounded-full text-xs font-bold uppercase tracking-wider mb-3">
                    <i class="fa-solid fa-chart-pie"></i>
                    <span>Data Analytics</span>
                </div>
                <h2 class="text-2xl md:text-3xl font-display font-bold dark:text-white text-police-900 mb-3">Crime Statistics Overview</h2>
                <p class="text-slate-600 dark:text-slate-400 max-w-xl mx-auto text-sm">Key metrics and insights into criminal activities across the nation. Updated in real-time.</p>
            </div>
            
            <!-- Professional Compact Stats Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-5 mb-8">
                <!-- Professional Case Status Card -->
                <div class="lg:col-span-2 professional-card bg-white dark:bg-police-900 p-5 rounded-2xl shadow-lg dark:shadow-2xl border border-slate-200 dark:border-police-800">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-5 gap-3">
                        <div>
                            <h3 class="font-display font-bold dark:text-white text-police-900">Case Status Overview</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Distribution of active cases</p>
                        </div>
                        <div class="text-right">
                            <span class="text-xs text-slate-500 dark:text-slate-400">Updated: <?php echo date('M d, Y'); ?></span>
                        </div>
                    </div>
                    
                    <div class="flex flex-col items-center">
                        <div class="relative w-40 h-40 mb-4">
                            <canvas id="compactCrimeChart" width="160" height="160"></canvas>
                            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                                <div class="text-2xl font-display font-bold dark:text-white text-police-900"><?php echo $total_criminals; ?></div>
                                <div class="text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Cases</div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-3 gap-3 w-full">
                            <div class="text-center p-3 bg-slate-50 dark:bg-police-800/50 rounded-lg">
                                <div class="text-lg font-display font-bold text-danger"><?php echo $graph_data['wanted']; ?></div>
                                <div class="text-xs text-slate-600 dark:text-slate-400 uppercase tracking-wider">Wanted</div>
                            </div>
                            <div class="text-center p-3 bg-slate-50 dark:bg-police-800/50 rounded-lg">
                                <div class="text-lg font-display font-bold text-success"><?php echo $graph_data['solved']; ?></div>
                                <div class="text-xs text-slate-600 dark:text-slate-400 uppercase tracking-wider">Solved</div>
                            </div>
                            <div class="text-center p-3 bg-slate-50 dark:bg-police-800/50 rounded-lg">
                                <div class="text-lg font-display font-bold text-accent"><?php echo $graph_data['custody']; ?></div>
                                <div class="text-xs text-slate-600 dark:text-slate-400 uppercase tracking-wider">Custody</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Key Metrics Cards -->
                <div class="space-y-5">
                    <!-- High Risk Alert Card -->
                    <div class="professional-card bg-gradient-to-br from-red-900/20 to-red-800/10 p-4 rounded-xl border border-red-800/30">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg bg-red-500/20 flex items-center justify-center">
                                    <i class="fa-solid fa-triangle-exclamation text-red-400 text-sm"></i>
                                </div>
                                <div>
                                    <h4 class="font-display font-bold text-white text-sm">High Risk Alert</h4>
                                    <p class="text-red-300 text-xs">Critical threats</p>
                                </div>
                            </div>
                        </div>
                        <div class="text-center">
                            <div class="text-3xl font-display font-bold text-white mb-1"><?php echo $graph_data['high_risk']; ?></div>
                            <p class="text-xs text-red-300/80">High threat individuals</p>
                            <div class="mt-3">
                                <div class="w-full h-1.5 bg-red-900/50 rounded-full overflow-hidden">
                                    <div class="h-full bg-gradient-to-r from-red-500 to-red-600 rounded-full" style="width: <?php echo min(100, ($graph_data['high_risk'] / max(1, $total_criminals)) * 100); ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Gang Activity Card -->
                    <div class="professional-card bg-white dark:bg-police-900 p-4 rounded-xl shadow dark:shadow-xl/50 border border-slate-200 dark:border-police-800/50">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg bg-orange-500/10 flex items-center justify-center">
                                    <i class="fa-solid fa-users text-orange-400 text-sm"></i>
                                </div>
                                <div>
                                    <h4 class="font-display font-medium dark:text-white text-police-900 text-sm">Gang Activity</h4>
                                    <p class="text-slate-500 dark:text-slate-400 text-xs">Organized crime</p>
                                </div>
                            </div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-display font-bold dark:text-white text-police-900 mb-1"><?php echo $graph_data['gang_activity']; ?></div>
                            <p class="text-xs text-slate-600 dark:text-slate-400">Gang-related cases</p>
                            <div class="mt-3">
                                <div class="w-full h-1.5 bg-slate-100 dark:bg-police-800 rounded-full overflow-hidden">
                                    <div class="h-full bg-gradient-to-r from-orange-500 to-amber-500 rounded-full" style="width: <?php echo min(100, ($graph_data['gang_activity'] / max(1, $total_criminals)) * 100); ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Performance Metrics -->
                <div class="space-y-5">
                    <!-- Clearance Rate Card -->
                    <div class="professional-card bg-gradient-to-br from-green-900/20 to-green-800/10 p-4 rounded-xl border border-green-800/30">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg bg-green-500/20 flex items-center justify-center">
                                    <i class="fa-solid fa-chart-line text-green-400 text-sm"></i>
                                </div>
                                <div>
                                    <h4 class="font-display font-bold text-white text-sm">Clearance Rate</h4>
                                    <p class="text-green-300 text-xs">Efficiency metric</p>
                                </div>
                            </div>
                        </div>
                        <div class="text-center">
                            <div class="text-3xl font-display font-bold text-white mb-1">
                                <?php echo $total_criminals > 0 ? round(($graph_data['solved'] / $total_criminals) * 100, 1) : 0; ?>%
                            </div>
                            <p class="text-xs text-green-300/80">Cases resolved</p>
                            <div class="mt-3">
                                <div class="w-full h-1.5 bg-green-900/50 rounded-full overflow-hidden">
                                    <div class="h-full bg-gradient-to-r from-green-500 to-green-600 rounded-full" style="width: <?php echo $total_criminals > 0 ? ($graph_data['solved'] / $total_criminals) * 100 : 0; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Nationality Distribution Card -->
                    <div class="professional-card bg-white dark:bg-police-900 p-4 rounded-xl shadow dark:shadow-xl/50 border border-slate-200 dark:border-police-800/50">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg bg-ncb-500/10 flex items-center justify-center">
                                    <i class="fa-solid fa-globe text-ncb-400 text-sm"></i>
                                </div>
                                <div>
                                    <h4 class="font-display font-medium dark:text-white text-police-900 text-sm">Top Nationalities</h4>
                                    <p class="text-slate-500 dark:text-slate-400 text-xs">Case distribution</p>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <?php 
                            $top_nationalities = array_slice($criminal_nationalities, 0, 3);
                            foreach($top_nationalities as $nation): 
                                $percentage = $total_criminals > 0 ? round(($nation['count'] / $total_criminals) * 100, 1) : 0;
                            ?>
                            <div>
                                <div class="flex justify-between text-xs mb-1">
                                    <span class="dark:text-slate-300 text-police-800 truncate"><?php echo $nation['nationality'] ?: 'Unknown'; ?></span>
                                    <span class="font-bold dark:text-white text-police-900"><?php echo $nation['count']; ?></span>
                                </div>
                                <div class="w-full h-1 bg-slate-100 dark:bg-police-800 rounded-full overflow-hidden">
                                    <div class="h-full bg-gradient-to-r from-ncb-400 to-ncb-500 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Stats Row -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="professional-card bg-white dark:bg-police-900 p-4 rounded-xl border border-slate-200 dark:border-police-800 text-center hover:shadow-md dark:hover:shadow-lg transition-all duration-300 group">
                    <div class="text-ncb-500 dark:text-ncb-400 text-xl mb-2">
                        <i class="fa-solid fa-user-clock"></i>
                    </div>
                    <div class="text-xl font-display font-bold dark:text-white text-police-900"><?php echo $graph_data['custody']; ?></div>
                    <p class="text-xs text-slate-600 dark:text-slate-400 uppercase tracking-wider font-medium mt-1">In Custody</p>
                </div>
                
                <div class="professional-card bg-white dark:bg-police-900 p-4 rounded-xl border border-slate-200 dark:border-police-800 text-center hover:shadow-md dark:hover:shadow-lg transition-all duration-300 group">
                    <div class="text-success text-xl mb-2">
                        <i class="fa-solid fa-handcuffs"></i>
                    </div>
                    <div class="text-xl font-display font-bold dark:text-white text-police-900"><?php echo $graph_data['solved']; ?></div>
                    <p class="text-xs text-slate-600 dark:text-slate-400 uppercase tracking-wider font-medium mt-1">Cases Solved</p>
                </div>
                
                <div class="professional-card bg-white dark:bg-police-900 p-4 rounded-xl border border-slate-200 dark:border-police-800 text-center hover:shadow-md dark:hover:shadow-lg transition-all duration-300 group">
                    <div class="text-warning text-xl mb-2">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </div>
                    <div class="text-xl font-display font-bold dark:text-white text-police-900"><?php echo $total_criminals - ($graph_data['solved'] + $graph_data['custody']); ?></div>
                    <p class="text-xs text-slate-600 dark:text-slate-400 uppercase tracking-wider font-medium mt-1">Under Investigation</p>
                </div>
                
                <div class="professional-card bg-white dark:bg-police-900 p-4 rounded-xl border border-slate-200 dark:border-police-800 text-center hover:shadow-md dark:hover:shadow-lg transition-all duration-300 group">
                    <div class="text-purple-500 text-xl mb-2">
                        <i class="fa-solid fa-chart-bar"></i>
                    </div>
                    <div class="text-xl font-display font-bold dark:text-white text-police-900">78.5%</div>
                    <p class="text-xs text-slate-600 dark:text-slate-400 uppercase tracking-wider font-medium mt-1">Clearance Rate</p>
                </div>
            </div>
        </div>
    </section>

    <!-- NEWS SECTION WITH ENHANCED DISPLAY -->
    <section id="news" class="py-16 bg-police-900 text-white">
        <div class="container mx-auto px-6">
            <div class="flex flex-col md:flex-row justify-between items-end mb-10 gap-4">
                <div>
                    <span class="text-ncb-400 font-bold tracking-[0.3em] uppercase text-xs mb-2 block">Live Updates</span>
                    <h2 class="text-3xl font-display font-bold text-white uppercase">NCB News & Updates</h2>
                    <div class="h-1 w-16 bg-ncb-500 mt-3 rounded-full"></div>
                </div>
                <div class="flex items-center gap-2 bg-police-800 px-4 py-2 rounded-full border border-police-700">
                    <span class="relative flex h-3 w-3">
                      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-ncb-400 opacity-75"></span>
                      <span class="relative inline-flex rounded-full h-3 w-3 bg-ncb-500"></span>
                    </span>
                    <span class="text-xs font-bold uppercase tracking-wide">Official News Feed</span>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- News Grid -->
                <div class="lg:col-span-2">
                    <!-- News Ticker -->
                    <div class="bg-police-800/50 rounded-xl p-5 border border-police-700 mb-6">
                        <div class="flex items-center gap-3 mb-3">
                            <i class="fa-solid fa-bolt text-danger"></i>
                            <h3 class="font-display text-lg font-bold">NCB News Alert</h3>
                        </div>
                        <div class="news-ticker-container h-7">
                            <div class="news-ticker-content">
                                <?php if (!empty($custom_news)): ?>
                                    <?php foreach($custom_news as $news): ?>
                                    <span class="text-sm mr-6">
                                        <span class="text-danger font-bold"><?php echo strtoupper($news['category'] ?? 'UPDATE'); ?>:</span>
                                        <span><?php echo htmlspecialchars($news['title']); ?></span>
                                        <span class="text-slate-400 ml-2">•</span>
                                    </span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-sm">Stay tuned for official NCB news updates...</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- News Grid -->
                    <div id="news-container" class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <?php if (!empty($custom_news)): ?>
                            <?php foreach($custom_news as $index => $news): ?>
                            <div class="professional-card group bg-police-800/50 rounded-xl overflow-hidden border border-police-700 hover:border-ncb-500/50 transition-all duration-300 flex flex-col h-full">
                                <div class="h-40 bg-police-900 relative overflow-hidden">
                                    <?php if (!empty($news['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($news['image_url']); ?>" 
                                             alt="<?php echo htmlspecialchars($news['title']); ?>"
                                             class="w-full h-full object-cover opacity-80 group-hover:opacity-100 group-hover:scale-105 transition duration-500"
                                             onerror="this.src='https://placehold.co/600x400/0f172a/3b82f6?text=NCB+News'">
                                    <?php else: ?>
                                        <div class="w-full h-full bg-gradient-to-br from-ncb-900 to-police-900 flex items-center justify-center">
                                            <i class="fa-solid fa-newspaper text-3xl text-ncb-500/50"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="absolute top-3 left-3">
                                        <span class="bg-<?php echo $news['priority'] == 1 ? 'danger' : ($news['priority'] == 2 ? 'warning' : 'ncb-600'); ?> text-white text-[10px] font-bold px-2.5 py-1 rounded-full uppercase tracking-wider">
                                            <?php echo $news['priority'] == 1 ? 'BREAKING' : ($news['priority'] == 2 ? 'IMPORTANT' : 'UPDATE'); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="p-5 flex-1 flex flex-col">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-xs text-ncb-400 font-bold uppercase tracking-wide"><?php echo htmlspecialchars($news['category'] ?? 'General'); ?></span>
                                        <span class="text-xs text-slate-500"><?php echo format_news_date($news['created_at']); ?></span>
                                    </div>
                                    <h4 class="font-display font-bold mb-2 leading-snug group-hover:text-ncb-400 transition line-clamp-2"><?php echo htmlspecialchars($news['title']); ?></h4>
                                    <p class="text-slate-400 text-sm mb-3 line-clamp-2"><?php echo htmlspecialchars(substr($news['content'], 0, 100)) . '...'; ?></p>
                                    <div class="mt-auto pt-3 border-t border-police-700 flex justify-between items-center">
                                        <span class="text-xs font-bold text-ncb-400 uppercase tracking-wide group-hover:underline">Read Full Story</span>
                                        <i class="fa-solid fa-arrow-right-long text-ncb-400 transform group-hover:translate-x-1 transition"></i>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <!-- Fallback message if no news -->
                            <div class="col-span-2 text-center py-10">
                                <i class="fa-solid fa-newspaper text-4xl text-slate-600 mb-3"></i>
                                <h3 class="text-xl font-display font-bold text-white mb-2">No News Available</h3>
                                <p class="text-slate-400">NCB news updates will appear here when published.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- News Sidebar -->
                <div class="space-y-5">
                    <!-- News Categories -->
                    <div class="glass-panel rounded-xl p-5">
                        <h3 class="font-display font-bold text-lg mb-3">News Categories</h3>
                        <div class="space-y-2">
                            <?php
                            $categories = [
                                ['name' => 'Breaking News', 'icon' => 'fa-triangle-exclamation', 'color' => 'danger', 'count' => 3],
                                ['name' => 'Security Updates', 'icon' => 'fa-shield-halved', 'color' => 'ncb-400', 'count' => 5],
                                ['name' => 'Arrest Reports', 'icon' => 'fa-handcuffs', 'color' => 'success', 'count' => 8],
                                ['name' => 'Safety Tips', 'icon' => 'fa-lightbulb', 'color' => 'warning', 'count' => 12],
                                ['name' => 'Community', 'icon' => 'fa-users', 'color' => 'blue-400', 'count' => 6]
                            ];
                            foreach($categories as $cat): 
                            ?>
                            <a href="#" class="flex items-center justify-between p-2.5 hover:bg-white/5 rounded-lg transition group">
                                <div class="flex items-center gap-3">
                                    <i class="fa-solid <?php echo $cat['icon']; ?> text-<?php echo $cat['color']; ?>"></i>
                                    <span class="text-sm"><?php echo $cat['name']; ?></span>
                                </div>
                                <span class="text-xs bg-<?php echo $cat['color']; ?>/20 text-<?php echo $cat['color']; ?> px-2 py-0.5 rounded"><?php echo $cat['count']; ?></span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Submit News Tip -->
                    <div class="glass-panel rounded-xl p-5 text-center">
                        <div class="w-14 h-14 mx-auto mb-3 rounded-full bg-ncb-500/20 flex items-center justify-center">
                            <i class="fa-solid fa-bullhorn text-ncb-400 text-xl"></i>
                        </div>
                        <h4 class="font-display font-bold mb-1">Have News Tips?</h4>
                        <p class="text-slate-400 text-xs mb-3">Report important information to NCB media team</p>
                        <a href="login.php" class="inline-block bg-ncb-600 hover:bg-ncb-700 text-white px-5 py-2 rounded-lg text-xs font-medium transition">
                            <i class="fa-solid fa-paper-plane mr-1.5"></i> Submit Tip
                        </a>
                    </div>
                    
                    <!-- News Archive -->
                    <div class="glass-panel rounded-xl p-5">
                        <h3 class="font-display font-bold text-lg mb-3">Recent Updates</h3>
                        <div class="space-y-2">
                            <?php 
                            // Get 3 most recent news items
                            $recent_news = array_slice($custom_news, 0, 3);
                            if (!empty($recent_news)):
                                foreach($recent_news as $recent): 
                            ?>
                            <a href="#" class="block p-2.5 hover:bg-white/5 rounded-lg transition group">
                                <div class="text-xs text-slate-400 mb-1"><?php echo format_news_date($recent['created_at']); ?></div>
                                <div class="text-sm text-white group-hover:text-ncb-400 transition line-clamp-2"><?php echo htmlspecialchars($recent['title']); ?></div>
                            </a>
                            <?php 
                                endforeach;
                            else: 
                            ?>
                            <div class="text-center py-4">
                                <p class="text-sm text-slate-400">No recent updates</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ... [Rest of the sections remain mostly unchanged, just updated for consistency] ... -->

    <!-- NEWS DETAIL MODAL -->
    <div id="news-modal" class="fixed inset-0 bg-black/80 z-[70] hidden items-center justify-center p-4">
        <div class="bg-white dark:bg-police-900 rounded-2xl max-w-3xl w-full max-h-[85vh] overflow-hidden">
            <div class="p-5 border-b border-slate-200 dark:border-police-800 flex justify-between items-center">
                <h3 class="font-display text-xl font-bold dark:text-white text-police-900">News Details</h3>
                <button onclick="closeNewsModal()" class="text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-white text-xl">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
            <div class="p-5 overflow-y-auto max-h-[65vh] custom-scrollbar" id="news-modal-content">
                <!-- Content loaded via JavaScript -->
            </div>
            <div class="p-5 border-t border-slate-200 dark:border-police-800 flex justify-end">
                <button onclick="closeNewsModal()" class="px-5 py-2.5 bg-ncb-600 hover:bg-ncb-700 text-white rounded-lg font-medium text-sm transition">Close</button>
            </div>
        </div>
    </div>

    <!-- BACK TO TOP BUTTON -->
    <button id="back-to-top" class="fixed bottom-6 right-6 w-10 h-10 bg-ncb-600 hover:bg-ncb-700 text-white rounded-full shadow-lg shadow-ncb-500/30 hidden transition-all duration-300 z-40 flex items-center justify-center" aria-label="Back to top">
        <i class="fa-solid fa-arrow-up text-sm"></i>
    </button>

    <!-- SCRIPTS -->
    <script>
        // Initialize compact charts
        function initCompactCharts() {
            // Hero compact chart
            const heroCtx = document.getElementById('heroCompactChart').getContext('2d');
            const heroChart = new Chart(heroCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Wanted', 'Solved', 'In Custody'],
                    datasets: [{
                        data: [<?php echo $graph_data['wanted']; ?>, <?php echo $graph_data['solved']; ?>, <?php echo $graph_data['custody']; ?>],
                        backgroundColor: ['#ef4444', '#10b981', '#3b82f6'],
                        borderWidth: 2,
                        borderColor: window.matchMedia('(prefers-color-scheme: dark)').matches ? '#1e293b' : '#ffffff',
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    cutout: '55%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            titleColor: '#e2e8f0',
                            bodyColor: '#cbd5e1',
                            borderColor: '#334155',
                            borderWidth: 1,
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    label += value + ' (' + percentage + '%)';
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
            
            // Main compact chart
            const mainCtx = document.getElementById('compactCrimeChart').getContext('2d');
            const mainChart = new Chart(mainCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Wanted', 'Solved', 'In Custody'],
                    datasets: [{
                        data: [<?php echo $graph_data['wanted']; ?>, <?php echo $graph_data['solved']; ?>, <?php echo $graph_data['custody']; ?>],
                        backgroundColor: ['#ef4444', '#10b981', '#3b82f6'],
                        borderWidth: 2,
                        borderColor: window.matchMedia('(prefers-color-scheme: dark)').matches ? '#1e293b' : '#ffffff',
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    cutout: '60%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            titleColor: '#e2e8f0',
                            bodyColor: '#cbd5e1',
                            borderColor: '#334155',
                            borderWidth: 1,
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    label += value + ' (' + percentage + '%)';
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // News modal functionality
        function openNewsModal(newsId) {
            // For now, just show a simple message
            // In production, you would fetch news details via AJAX
            document.getElementById('news-modal-content').innerHTML = `
                <div class="text-center py-8">
                    <div class="inline-block animate-spin rounded-full h-10 w-10 border-t-2 border-b-2 border-ncb-500 mb-3"></div>
                    <p class="text-slate-600 dark:text-slate-400 text-sm">Loading news details...</p>
                </div>
            `;
            document.getElementById('news-modal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        
        function closeNewsModal() {
            document.getElementById('news-modal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
        
        // Initialize everything when DOM is loaded
        document.addEventListener('DOMContentLoaded', () => {
            // Initialize theme toggle
            function initTheme() {
                const html = document.getElementById('html-theme');
                const themeToggle = document.getElementById('theme-toggle');
                const themeIcon = document.getElementById('theme-icon');
                
                const savedTheme = localStorage.getItem('theme') || 'dark';
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                
                if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
                    html.classList.add('dark');
                    themeIcon.className = 'fa-solid fa-sun text-yellow-300';
                } else {
                    html.classList.remove('dark');
                    themeIcon.className = 'fa-solid fa-moon text-slate-700';
                }
                
                themeToggle.addEventListener('click', () => {
                    if (html.classList.contains('dark')) {
                        html.classList.remove('dark');
                        localStorage.setItem('theme', 'light');
                        themeIcon.className = 'fa-solid fa-moon text-slate-700';
                    } else {
                        html.classList.add('dark');
                        localStorage.setItem('theme', 'dark');
                        themeIcon.className = 'fa-solid fa-sun text-yellow-300';
                    }
                });
            }
            
            // Initialize back to top button
            function initBackToTop() {
                const backToTop = document.getElementById('back-to-top');
                
                window.addEventListener('scroll', () => {
                    if (window.scrollY > 300) {
                        backToTop.classList.remove('hidden');
                    } else {
                        backToTop.classList.add('hidden');
                    }
                });
                
                backToTop.addEventListener('click', () => {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });
            }
            
            // Initialize navbar scroll effect
            function initNavbarScroll() {
                const navbar = document.getElementById('navbar');
                let lastScroll = 0;
                
                window.addEventListener('scroll', () => {
                    const currentScroll = window.pageYOffset;
                    
                    if (currentScroll <= 0) {
                        navbar.classList.remove('shadow-2xl');
                        navbar.style.transform = 'translateY(0)';
                        return;
                    }
                    
                    if (currentScroll > lastScroll && currentScroll > 100) {
                        navbar.style.transform = 'translateY(-100%)';
                    } else {
                        navbar.style.transform = 'translateY(0)';
                        navbar.classList.add('shadow-2xl');
                    }
                    
                    lastScroll = currentScroll;
                });
            }
            
            // Initialize all components
            initTheme();
            initCompactCharts();
            initBackToTop();
            initNavbarScroll();
            
            // Add smooth scrolling for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    const href = this.getAttribute('href');
                    if (href === '#') return;
                    
                    e.preventDefault();
                    const target = document.querySelector(href);
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth' });
                    }
                });
            });
            
            // Close modals on ESC key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    closeNewsModal();
                }
            });
        });
    </script>
</body>
</html>