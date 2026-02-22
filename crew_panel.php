<?php
session_start();
$conn = new mysqli("localhost", "root", "", "altitude_x");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'crew') {
    header("Location: login.php"); 
    exit();
}
$user_id = $_SESSION['user_id'];
$crew_res = $conn->query("SELECT * FROM crew WHERE employee_id = $user_id");
$crew_data = $crew_res->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Altitude X | Crew Terminal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;800&family=Syncopate:wght@700&display=swap');
        
        :root { 
            --pastel-sky: #bae6fd; 
            --mustard: #EAB308; 
            --mustard-border: #fde047; 
            --deep-navy: #0F172A; 
            --sky-bright: #0ea5e9;
            --neutral-grey: #94a3b8; 
            --light-green: #dcfce7;
            --dark-green: #166534;
        }
        
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #ffffff; color: var(--deep-navy); margin: 0; }

        /* SIDEBAR */
        .sidebar { width: 260px; background: var(--deep-navy); color: white; position: fixed; height: 100vh; z-index: 50; transform: translateX(-100%); }
        .logo-pillar { width: 4px; height: 35px; background: var(--mustard); box-shadow: 0 0 15px var(--mustard); }
        .logo-x { font-family: 'Syncopate', sans-serif; font-size: 1.6rem; color: white; line-height: 1; }

        /* CREW CARD BASE */
        .crew-card { 
            background: #ffffff; border-radius: 1.5rem; 
            opacity: 0;
            /* Changed to a standard ease-out to prevent 'bouncing' overlaps */
            transition: transform 0.3s ease-out, box-shadow 0.3s ease-out, border-color 0.3s ease-out;
            will-change: transform;
        }

        /* BLUE THEME */
        .card-blue { 
            border: 2px solid var(--pastel-sky);
            border-bottom: 6px solid var(--sky-bright); 
        }
        .card-blue:hover { 
            border-color: var(--sky-bright); 
            transform: translateY(-8px); /* Reduced distance to prevent overlap */
            box-shadow: 0 15px 30px rgba(14, 165, 233, 0.1); 
        }

        /* MUSTARD THEME */
        .card-mustard { 
            border: 2px solid var(--mustard-border);
            border-bottom: 6px solid var(--mustard); 
        }
        .card-mustard:hover { 
            border-color: var(--mustard);
            transform: translateY(-8px); 
            box-shadow: 0 15px 30px rgba(234, 179, 8, 0.15); 
        }

        .card-label { font-size: 10px; font-weight: 800; color: var(--sky-bright); text-transform: uppercase; letter-spacing: 0.15em; margin-bottom: 8px; }
        .card-value { font-size: 1.5rem; font-weight: 800; color: #1e293b; }

        .badge-confirmed { background: var(--light-green); color: var(--dark-green); padding: 6px 14px; border-radius: 8px; font-size: 10px; font-weight: 800; text-transform: uppercase; }
    </style>
</head>
<body class="flex">

    <aside class="sidebar p-8 flex flex-col shadow-2xl">
        <div class="flex items-center gap-3 mb-16">
            <div class="logo-pillar"></div>
            <div>
                <p class="text-[8px] tracking-[0.4em] text-slate-400 font-bold uppercase">Altitude</p>
                <h1 class="logo-x">X<span class="text-[var(--sky-bright)]">.</span></h1>
            </div>
        </div>
        <nav class="space-y-4 flex-1">
            <a href="#" class="flex items-center gap-4 text-white p-4 bg-white/5 rounded-2xl border-l-4 border-[var(--mustard)]">
                <i class="fas fa-user-shield text-[var(--mustard)]"></i> Crew Deck
            </a>
            <a href="logout.php" class="flex items-center gap-4 text-red-400 p-4 hover:bg-red-400/10 rounded-2xl mt-auto transition">
                <i class="fas fa-power-off"></i> Sign Out
            </a>
        </nav>
    </aside>

    <main class="flex-1 ml-[260px] p-12">
        <header class="mb-12 flex justify-between items-center" id="header">
            <div>
                <h2 class="text-5xl font-black text-slate-900 tracking-tighter">Crew <span class="text-[var(--mustard)] italic">Deck.</span></h2>
                <p class="text-[var(--sky-bright)] font-extrabold text-[10px] uppercase tracking-[0.4em] mt-2">Active Flight Personnel</p>
            </div>

            <div class="flex items-center gap-6">
                <div class="text-right hidden lg:block">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Duty Status</p>
                    <div class="flex items-center justify-end gap-2">
                        <span class="text-xs font-bold text-slate-700">ACTIVE</span>
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                        </span>
                    </div>
                </div>
                <div class="h-10 w-[1px] bg-slate-200"></div>
                <div class="flex items-center gap-3 bg-slate-50 p-2 pr-4 rounded-2xl border border-slate-100">
                    <div class="h-10 w-10 bg-[var(--mustard)] rounded-xl flex items-center justify-center text-white font-black shadow-lg shadow-yellow-100">
                        <?php echo strtoupper(substr($crew_data['e_name'] ?? 'CR', 0, 2)); ?>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase leading-none">Personnel</p>
                        <p class="text-xs font-bold text-slate-700">#<?php echo $user_id; ?></p>
                    </div>
                </div>
            </div>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
            <div class="crew-card card-blue p-8">
                <p class="card-label">Personnel Name</p>
                <h2 class="card-value"><?php echo $crew_data['e_name']; ?></h2>
            </div>
            
            <div class="crew-card card-mustard p-8">
                <p class="card-label text-[var(--mustard)]">Training Level</p>
                <h2 class="card-value"><?php echo $crew_data['training_level']; ?></h2>
            </div>

            <div class="crew-card card-blue p-8">
                <p class="card-label">Flight Experience</p>
                <h2 class="card-value"><?php echo $crew_data['flight_hours']; ?> <span class="text-xs">HRS</span></h2>
            </div>
        </div>

        <div class="crew-card card-blue overflow-hidden">
            <div class="p-6 bg-slate-900 text-white flex justify-between items-center">
                <h3 class="text-xs font-black uppercase tracking-[0.2em]">Assignment Schedule</h3>
                <i class="fas fa-plane-arrival text-[var(--mustard)]"></i>
            </div>
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 border-b">
                    <tr>
                        <th class="p-6 text-[var(--sky-bright)] text-[10px] uppercase font-black">Flight</th>
                        <th class="p-6 text-[var(--sky-bright)] text-[10px] uppercase font-black">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php
                    $emp_id = $crew_data['employee_id'];
                    $res = $conn->query("SELECT * FROM operated_by WHERE employee_id = $emp_id");

                    if ($res && $res->num_rows > 0) {
                        while($row = $res->fetch_row()) {
                            $flight_id = $row[1]; 
                            echo "<tr>";
                            echo "<td class='p-6 font-bold text-slate-700'>FLIGHT <span class='text-[var(--mustard)] font-mono'>#$flight_id</span></td>";
                            echo "<td class='p-6'><span class='badge-confirmed'>CONFIRMED</span></td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='2' class='p-16 text-center text-slate-400 font-bold italic uppercase text-xs tracking-widest'>No Assigned Missions</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        window.onload = () => {
            // Updated timeline to be smoother and less 'springy'
            gsap.timeline({defaults: {ease: "expo.out", duration: 1.0}})
                .to(".sidebar", { x: 0 })
                .from("#header", { opacity: 0, y: -15 }, "-=0.7")
                .to(".crew-card", { 
                    opacity: 1, 
                    y: 0, 
                    stagger: 0.1, 
                    onComplete: function() { 
                        // Crucial: Clear transform so the CSS hover transition works cleanly
                        gsap.set(".crew-card", { clearProps: "y" }); 
                    }
                }, "-=0.5");
        };
    </script>
</body>
</html>