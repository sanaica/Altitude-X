<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'passenger') {
    header("Location: login.php"); 
    exit();
}
$conn = new mysqli("localhost", "root", "", "altitude_x");

$airports = [];
$result = $conn->query("SELECT * FROM airports ORDER BY country, city");
while($row = $result->fetch_assoc()) {
    $group_name = $row['country'] . " (" . $row['city'] . ")";
    $airports[$group_name][] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Altitude X | Source & Destination</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;800&family=Syncopate:wght@700&display=swap');
        
        :root { 
            --mustard: #EAB308; 
            --sky: #0ea5e9; 
            --navy: #0F172A; 
            --soft-grey: #94a3b8;
            --success-green: #22c55e;
            --mustard-light: #fefce8;
        }
        
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #ffffff; color: var(--navy); margin: 0; overflow-x: hidden; }

        /* SIDEBAR */
        .sidebar { width: 260px; background: var(--navy); color: white; position: fixed; height: 100vh; z-index: 50; }
        .logo-pillar { width: 4px; height: 35px; background: var(--mustard); box-shadow: 0 0 15px var(--mustard); }
        .logo-x { font-family: 'Syncopate', sans-serif; font-size: 1.6rem; color: white; line-height: 1; }

        /* BOOKING CARD */
        .booking-card { 
            background: #ffffff; border-radius: 2.5rem; border: 1px solid #f1f5f9;
            border-bottom: 8px solid var(--sky); box-shadow: 0 40px 80px -15px rgba(15, 23, 42, 0.08);
            max-width: 520px; opacity: 0; transform: translateY(30px);
        }

        .label-main { 
            font-size: 0.7rem; font-weight: 800; color: #000000; 
            text-transform: uppercase; letter-spacing: 0.2em; margin-bottom: 10px; display: block;
        }
        
        /* BASE INPUT STYLE */
        .form-select, .form-input { 
            background: #f8fafc; border: 2px solid #f1f5f9; border-radius: 16px; 
            padding: 18px; width: 100%; outline: none; transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); 
            font-weight: 600; color: var(--soft-grey); font-size: 0.85rem; appearance: none;
        }

        /* --- UNIFIED YELLOW ACTIVE STATE --- */
        .input-active { 
            color: #854d0e !important; /* Richer Brownish-Black for contrast on yellow */
            border-color: var(--mustard) !important;
            background-color: var(--mustard-light) !important;
            box-shadow: 0 4px 12px rgba(234, 179, 8, 0.1);
        }
        
        .form-select:focus, .form-input:focus { border-color: var(--sky); background-color: white; }

        .select-wrapper { position: relative; }
        .select-wrapper::after {
            content: '\f107'; font-family: 'Font Awesome 6 Free'; font-weight: 900;
            position: absolute; right: 20px; top: 50%; transform: translateY(-50%);
            color: var(--soft-grey); pointer-events: none;
        }

        .btn-search {
            background: var(--navy); color: white; font-weight: 800; padding: 22px; 
            border-radius: 18px; width: 100%; border: none; text-transform: uppercase; 
            letter-spacing: 0.3em; cursor: pointer; display: flex; align-items: center; 
            justify-content: center; gap: 14px; transition: 0.5s; font-size: 0.75rem;
        }
        .btn-search:hover { background: var(--sky); transform: translateY(-3px); }

        /* STATUS DOT */
        .status-dot {
            height: 10px; width: 10px; background-color: var(--success-green);
            border-radius: 50%; display: inline-block;
            box-shadow: 0 0 12px 4px rgba(34, 197, 94, 0.4); border: 2px solid #ffffff;
        }

        #titleContainer { opacity: 0; }
        #resultsArea { max-width: 520px; width: 100%; margin-top: 2rem; display: none; opacity: 0; }
    </style>
</head>
<body class="flex">

    <aside class="sidebar p-8 flex flex-col shadow-2xl">
        <div class="flex items-center gap-3 mb-16">
            <div class="logo-pillar"></div>
            <div>
                <p class="text-[8px] tracking-[0.4em] text-slate-400 font-bold uppercase">Altitude</p>
                <h1 class="logo-x">X<span class="text-[var(--sky)]">.</span></h1>
            </div>
        </div>
        <nav class="space-y-4 flex-1">
            <a href="passenger_panel.php" class="flex items-center gap-4 text-slate-400 p-4 hover:text-white transition">
                <i class="fas fa-th-large"></i> Dashboard
            </a>
            <a href="#" class="flex items-center gap-4 text-white p-4 bg-white/5 rounded-2xl border-l-4 border-[var(--mustard)]">
                <i class="fas fa-plane-departure text-[var(--mustard)]"></i> Book Flight
            </a>
            <a href="logout.php" class="flex items-center gap-4 text-red-400 p-4 mt-auto">
                <i class="fas fa-power-off"></i> Sign Out
            </a>
        </nav>
    </aside>

    <main class="flex-1 ml-[260px] p-12 bg-white min-h-screen relative flex flex-col items-center justify-center">
        
        <header class="absolute top-12 right-12 flex items-center gap-4">
            <div class="text-right">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Global Terminal</p>
                <p class="text-[11px] font-bold text-slate-700 uppercase mt-1 flex items-center justify-end gap-3">
                    <span class="status-dot"></span> System Online
                </p>
            </div>
        </header>

        <div class="mb-8 text-center" id="titleContainer">
            <h2 class="text-6xl font-black text-slate-900 tracking-tighter">
                Book Your <span class="text-[var(--mustard)] italic">Journey</span><span class="text-[var(--sky)]">.</span>
            </h2>
            <p class="text-[var(--sky)] font-extrabold text-[10px] uppercase tracking-[0.6em] mt-3">
                Altitude X Elite Reservations
            </p>
        </div>

        <div class="booking-card p-12 w-full" id="bookingCard">
            <form id="searchForm" class="space-y-8">
                <div>
                    <label class="label-main">Departure Hub</label>
                    <div class="select-wrapper">
                        <select name="from_airport" class="form-select dynamic-field" required>
                            <option value="" disabled selected>Select Source</option>
                            <?php foreach($airports as $group => $ap_list): ?>
                                <optgroup label='<?php echo htmlspecialchars($group); ?>'>
                                    <?php foreach($ap_list as $ap): ?>
                                        <option value='<?php echo $ap['airport_code']; ?>'><?php echo $ap['airport_name']; ?> (<?php echo $ap['airport_code']; ?>)</option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="label-main">Arrival Hub</label>
                    <div class="select-wrapper">
                        <select name="to_airport" class="form-select dynamic-field" required>
                            <option value="" disabled selected>Select Destination</option>
                            <?php foreach($airports as $group => $ap_list): ?>
                                <optgroup label='<?php echo htmlspecialchars($group); ?>'>
                                    <?php foreach($ap_list as $ap): ?>
                                        <option value='<?php echo $ap['airport_code']; ?>'><?php echo $ap['airport_name']; ?> (<?php echo $ap['airport_code']; ?>)</option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="label-main">Travel Date</label>
                    <input type="text" onfocus="(this.type='date')" name="travel_date" placeholder="DD MM YYYY" class="form-input dynamic-field" required>
                </div>

                <div class="pt-4">
                    <button type="submit" class="btn-search" id="submitBtn">
                        <span>Check Availability</span>
                        <i class="fas fa-arrow-right text-[10px] opacity-40"></i>
                    </button>
                </div>
            </form>
        </div>

        <div id="resultsArea"></div>

    </main>

    <script>
        window.onload = () => {
            const tl = gsap.timeline({ defaults: { ease: "power4.out" }});
            tl.to("#titleContainer", { opacity: 1, y: 0, startAt: { y: -80 }, duration: 1.5, ease: "back.out(1.2)" })
              .to("#bookingCard", { opacity: 1, y: 0, duration: 1.2 }, "-=1"); 
        };

        // Unified Yellow Active Logic
        document.querySelectorAll('.dynamic-field').forEach(el => {
            el.addEventListener('change', () => {
                if(el.value) {
                    el.classList.add('input-active');
                }
            });
        });

        // Search Action
        document.getElementById('searchForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            const resultsArea = document.getElementById('resultsArea');
            btn.innerHTML = `<span>Searching...</span> <i class="fas fa-circle-notch animate-spin"></i>`;
            
            setTimeout(() => {
                btn.innerHTML = `<span>Check Availability</span> <i class="fas fa-arrow-right text-[10px] opacity-40"></i>`;
                resultsArea.style.display = 'block';
                resultsArea.innerHTML = `<div class="p-6 bg-slate-50 rounded-2xl border-l-4 border-[var(--mustard)] font-bold text-slate-800 text-sm">Searching Altitude X Database for luxury connections...</div>`;
                gsap.fromTo(resultsArea, { opacity: 0, y: 10 }, { opacity: 1, y: 0, duration: 0.5 });
            }, 800);
        });
    </script>
</body>
</html>