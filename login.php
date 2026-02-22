<?php
session_start();
// 1. DATABASE CONNECTION
$conn = new mysqli("localhost", "root", "", "altitude_x");

if ($conn->connect_error) { 
    die("Connection failed: " . $conn->connect_error); 
}

$error_msg = "";

// 2. FIXED LOGIC FOR ALL 3 ROLES
if (isset($_POST['login_btn'])) {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password']; 

    $query = "SELECT * FROM users WHERE username='$username' AND password='$password'";
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Setup Sessions
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = strtolower($user['role']); // Handle "Crew", "crew", or "CREW"

        // --- THE ROUTING SWITCH ---
        if ($_SESSION['role'] == 'admin') {
            header("Location: admin_panel.php");
            exit();
        } 
        else if ($_SESSION['role'] == 'crew') {
            header("Location: crew_panel.php"); 
            exit();
        } 
        else {
            // Default for 'user' or 'passenger'
            header("Location: passenger_panel.php");
            exit();
        }
    } else {
        $error_msg = "❌ Invalid credentials. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Altitude X | Elite Access</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;800&family=Syncopate:wght@700&display=swap');
        
        :root { --mustard: #EAB308; --sky: #0EA5E9; --slate: #0F172A; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #ffffff; overflow: hidden; margin: 0; }

        .hero-shard { 
            background: var(--slate); 
            clip-path: polygon(15% 0%, 100% 0%, 100% 100%, 0% 100%); 
            transform: translateX(100%); 
        }

        .form-side { transform: translateX(-100%); }

        .logo-pillar { width: 6px; height: 80px; background: var(--mustard); border-radius: 4px; }
        .logo-x { font-family: 'Syncopate', sans-serif; font-size: 5rem; color: var(--slate); line-height: 0.9; }

        .form-input { 
            background: #f8fafc; border: 2px solid #f1f5f9; border-radius: 1rem; 
            padding: 1.25rem 1.25rem 1.25rem 4rem; width: 100%; outline: none; transition: 0.3s;
        }
        .form-input:focus { border-color: var(--mustard); background: white; }

        .btn-elite {
            background: var(--mustard); color: var(--slate); font-weight: 800;
            padding: 1.25rem; border-radius: 1rem; width: 100%; border: none;
            text-transform: uppercase; letter-spacing: 0.3em; cursor: pointer;
        }

        .text-reveal { overflow: hidden; }
        .text-item { transform: translateY(100%); opacity: 0; }
    </style>
</head>
<body class="min-h-screen flex">

    <div class="w-full lg:w-[45%] flex flex-col justify-center p-12 lg:p-24 bg-white z-10 form-side">
        
        <div class="flex items-center gap-4 mb-16 text-reveal">
            <div class="logo-pillar text-item"></div>
            <div class="text-item">
                <p class="text-[10px] tracking-[0.5em] text-slate-400 font-bold uppercase">Altitude</p>
                <h1 class="logo-x">X<span class="text-[var(--sky)]">.</span></h1>
            </div>
        </div>

        <div class="max-w-md w-full">
            <div class="text-reveal">
                <h2 class="text-6xl font-extrabold text-slate-900 mb-2 tracking-tighter leading-none text-item">Fly Beyond</h2>
            </div>
            <div class="text-reveal">
                <h2 class="text-6xl font-extrabold text-[var(--mustard)] italic mb-10 tracking-tighter leading-none text-item">The Clouds.</h2>
            </div>

            <?php if($error_msg): ?>
                <div class="bg-red-50 text-red-600 p-4 rounded-xl mb-6 font-bold border-l-4 border-red-500">
                    <?php echo $error_msg; ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="space-y-6">
                <div class="relative text-reveal">
                    <div class="text-item">
                        <i class="fas fa-user-pilot absolute left-6 top-1/2 -translate-y-1/2 text-slate-300"></i>
                        <input type="text" name="username" required class="form-input" placeholder="ACCESS IDENTIFIER">
                    </div>
                </div>

                <div class="relative text-reveal">
                    <div class="text-item">
                        <i class="fas fa-key absolute left-6 top-1/2 -translate-y-1/2 text-slate-300"></i>
                        <input type="password" name="password" required class="form-input" placeholder="SECURITY KEY">
                    </div>
                </div>

                <div class="text-reveal">
                    <button type="submit" name="login_btn" class="btn-elite shadow-xl text-item">
                        Authorize Access
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="hidden lg:flex lg:w-[55%] hero-shard relative items-center justify-center overflow-hidden">
        <img id="planeHero" src="https://images.unsplash.com/photo-1540962351504-03099e0a754b?auto=format&fit=crop&q=80&w=1400" 
             class="absolute inset-0 w-full h-full object-cover scale-150 opacity-90" alt="Private Jet">
        
        <div id="hudBox" class="relative z-20 p-10 bg-white/10 backdrop-blur-md border border-white/20 rounded-[2.5rem] mr-20 opacity-0">
            <h3 class="text-white text-4xl font-black italic tracking-tighter uppercase leading-none">
                Visibility <br><span class="text-[var(--mustard)]">Unlimited</span>
            </h3>
        </div>
    </div>

    <script>
        window.onload = () => {
            const masterTl = gsap.timeline({defaults: {ease: "power4.out", duration: 1.5}});

            masterTl.to(".form-side", { x: "0%", duration: 1.8 })
                    .to(".hero-shard", { x: "0%", duration: 1.8 }, "-=1.8")
                    .to(".text-item", { y: 0, opacity: 1, stagger: 0.1, duration: 1 }, "-=0.8")
                    .to("#planeHero", { scale: 1.1, duration: 3, ease: "expo.out" }, "-=1.5")
                    .to("#hudBox", { opacity: 1, y: 0, duration: 1 }, "-=1");

            document.addEventListener("mousemove", (e) => {
                const xVal = (e.clientX / window.innerWidth - 0.5) * 30;
                const yVal = (e.clientY / window.innerHeight - 0.5) * 30;
                gsap.to("#planeHero", { x: xVal, y: yVal, duration: 2 });
                gsap.to("#hudBox", { x: -xVal * 0.5, y: -yVal * 0.5, duration: 2.5 });
            });
        };
    </script>
</body>
</html>