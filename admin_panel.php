<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php"); 
    exit();
}
$conn = new mysqli("localhost", "root", "", "altitude_x");

// --- DATA FETCHING ---
$airports = $conn->query("SELECT airport_code, city FROM airports ORDER BY city");
$airport_list = "";
while($row = $airports->fetch_assoc()) {
    $airport_list .= "<option value='".$row['airport_code']."'>".$row['city']." (".$row['airport_code'].")</option>";
}

$aircrafts = $conn->query("SELECT tail_no FROM aircraft");
$aircraft_options = "";
while($a = $aircrafts->fetch_assoc()) {
    $aircraft_options .= "<option value='".$a['tail_no']."'>".$a['tail_no']."</option>";
}

$instances = $conn->query("SELECT flight_no FROM flight_instance");
$instance_options = "";
while($i = $instances->fetch_assoc()) {
    $instance_options .= "<option value='".$i['flight_no']."'>".$i['flight_no']."</option>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Altitude X | Flight Deck</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;800&family=Syncopate:wght@700&display=swap');
        
        :root { 
            --pastel-sky: #bae6fd; --sky-bright: #0ea5e9;
            --pastel-mustard: #fef08a; --mustard: #EAB308;        
            --deep-navy: #0F172A; --neutral-grey: #94a3b8; 
            --soft-black: #1e293b; --success-green: #16a34a;
        }
        
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #ffffff; color: var(--deep-navy); margin: 0; overflow-x: hidden; }

        .sidebar { width: 260px; background: var(--deep-navy); color: white; position: fixed; height: 100vh; z-index: 50; transform: translateX(-100%); }
        .logo-pillar { width: 4px; height: 35px; background: var(--mustard); box-shadow: 0 0 15px var(--mustard); }
        .logo-x { font-family: 'Syncopate', sans-serif; font-size: 1.6rem; color: white; line-height: 1; }

        /* --- THE MOVING CARDS LOGIC --- */
        .command-tab, .history-tab { 
            background: #ffffff; border-radius: 1.5rem; 
            border: 2px solid transparent; 
            opacity: 0; 
            display: flex; flex-direction: column;
            min-height: 480px; 
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.2) !important;
            cursor: default;
            will-change: transform;
        }

        .command-tab:hover, .history-tab:hover {
            transform: translateY(-12px) !important; 
        }

        /* SEAMLESS GLOWING OUTLINES */
        .border-sky-glow { border: 2px solid #bae6fd; border-bottom: 6px solid var(--sky-bright); }
        .border-sky-glow:hover { 
            border-color: var(--sky-bright); 
            box-shadow: 0 0 25px rgba(14, 165, 233, 0.2), 0 25px 50px -12px rgba(15, 23, 42, 0.1); 
        }

        .border-mustard-glow { border: 2px solid #fef08a; border-bottom: 6px solid var(--mustard); }
        .border-mustard-glow:hover { 
            border-color: var(--mustard); 
            box-shadow: 0 0 25px rgba(234, 179, 8, 0.2), 0 25px 50px -12px rgba(15, 23, 42, 0.1); 
        }

        /* INTERNAL FIELD BORDERS - SHARPENED VISIBILITY */
        .form-input { 
            background: #f8fafc; border-radius: 12px; 
            padding: 12px 16px; width: 100%; outline: none; transition: 0.3s; font-size: 0.85rem;
            color: var(--neutral-grey); font-weight: 600; 
            border: 2px solid #e2e8f0; /* Base Fallback */
        }

        /* Visible Blue Internal Borders */
        .border-sky-glow .form-input { border-color: #7dd3fc; } 
        .border-sky-glow .form-input:focus { border-color: var(--sky-bright); box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.15); color: #000; }

        /* Visible Mustard Internal Borders */
        .border-mustard-glow .form-input { border-color: #fde047; } 
        .border-mustard-glow .form-input:focus { border-color: var(--mustard); box-shadow: 0 0 0 4px rgba(234, 179, 8, 0.15); color: #000; }

        .form-input.filled { color: #000000 !important; }

        .tab-title { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.15em; color: var(--sky-bright); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px; }

        /* BUTTONS */
        .tab-btn { 
            padding: 14px; border-radius: 12px; width: 100%; border: none; 
            text-transform: uppercase; letter-spacing: 0.1em; cursor: pointer; 
            font-size: 0.75rem; font-weight: 800; 
            transition: all 0.3s ease-out;
            margin-top: auto; 
        }
        .tab-btn:hover { transform: translateY(-4px); filter: brightness(1.05); }
        .tab-btn-blue { background: var(--pastel-sky); color: var(--deep-navy); }
        .tab-btn-blue:hover { background: var(--sky-bright); color: white; }
        .tab-btn-mustard { background: var(--pastel-mustard); color: var(--deep-navy); }
        .tab-btn-mustard:hover { background: var(--mustard); color: white; }

        .status-msg { font-size: 9px; font-weight: 800; color: var(--success-green); text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 8px; text-align: center; opacity: 0; pointer-events: none; }
        #delay_box { display: none; }
        .badge { padding: 6px 14px; border-radius: 8px; font-size: 10px; font-weight: 800; text-transform: uppercase; }
        .status-on-time { background: #dcfce7; color: #166534; }
        .status-delayed { background: #fef9c3; color: #854d0e; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        .manifest-scroll { max-height: 400px; overflow-y: auto; }
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
                <i class="fas fa-compass text-[var(--mustard)]"></i> Flight Deck
            </a>
            <a href="logout.php" class="flex items-center gap-4 text-red-400 p-4 hover:bg-red-400/10 rounded-2xl mt-auto transition">
                <i class="fas fa-power-off"></i> Sign Out
            </a>
        </nav>
    </aside>

    <main class="flex-1 ml-[260px] p-12">
        <header class="mb-12 flex justify-between items-center" id="header">
            <div>
                <h2 class="text-5xl font-black text-slate-900 tracking-tighter">Flight <span class="text-[var(--mustard)] italic">Deck</span><span class="text-[var(--sky-bright)]">.</span></h2>
                <p class="text-[var(--sky-bright)] font-extrabold text-[10px] uppercase tracking-[0.4em] mt-2">Altitude X Systems Control</p>
            </div>
            <div class="flex items-center gap-4 bg-slate-50 border border-slate-200 p-2 pr-6 rounded-full">
                <div class="h-10 w-10 bg-slate-900 rounded-full flex items-center justify-center text-[var(--mustard)]">
                    <i class="fas fa-clock animate-pulse"></i>
                </div>
                <div class="flex flex-col">
                    <span class="text-[8px] font-black text-slate-400 uppercase">System Time</span>
                    <span id="live-clock" class="text-sm font-bold text-slate-900">00:00:00</span>
                </div>
            </div>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
            <div class="command-tab p-6 border-sky-glow">
                <div class="tab-title"><i class="fas fa-map-pin"></i> Terminals</div>
                <form class="flex flex-col h-full admin-form" data-msg="HUB LINKED">
                    <div class="space-y-4">
                        <input type="text" placeholder="ICAO CODE" class="form-input" required>
                        <input type="text" placeholder="CITY NAME" class="form-input" required>
                    </div>
                    <div class="flex-grow"></div>
                    <div class="status-msg">HUB LINKED</div>
                    <button type="submit" class="tab-btn tab-btn-blue">Link Hub</button>
                </form>
            </div>

            <div class="command-tab p-6 border-sky-glow">
                <div class="tab-title"><i class="fas fa-route"></i> Route Map</div>
                <form class="flex flex-col h-full admin-form" data-msg="ROUTE REGISTERED">
                    <div class="space-y-4">
                        <input type="text" placeholder="NEW FLIGHT ID" class="form-input" required>
                        <select class="form-input" required><option value="" disabled selected>ORIGIN</option><?php echo $airport_list; ?></select>
                        <select class="form-input" required><option value="" disabled selected>DESTINATION</option><?php echo $airport_list; ?></select>
                    </div>
                    <div class="flex-grow"></div>
                    <div class="status-msg">ROUTE REGISTERED</div>
                    <button type="submit" class="tab-btn tab-btn-blue">Register</button>
                </form>
            </div>

            <div class="command-tab p-6 border-mustard-glow">
                <div class="tab-title text-[var(--mustard)]"><i class="fas fa-plane-departure"></i> Deployment</div>
                <form class="flex flex-col h-full admin-form" data-msg="SYSTEM LIVE">
                    <div class="space-y-4">
                        <select class="form-input" required><option value="" disabled selected>AIRCRAFT ID</option><?php echo $aircraft_options; ?></select>
                        <input type="text" onfocus="(this.type='date')" placeholder="DATE" class="form-input" required>
                    </div>
                    <div class="flex-grow"></div>
                    <div class="status-msg">SYSTEM LIVE</div>
                    <button type="submit" class="tab-btn tab-btn-mustard">Go Live</button>
                </form>
            </div>

            <div class="command-tab p-6 border-mustard-glow">
                <div class="tab-title text-[var(--mustard)]"><i class="fas fa-sync-alt"></i> Operations</div>
                <form class="flex flex-col h-full admin-form" data-msg="STATE OVERRIDDEN">
                    <div class="space-y-4">
                        <select class="form-input" required><option value="" disabled selected>TARGET FLIGHT</option><?php echo $instance_options; ?></select>
                        <select id="st_select" class="form-input" required>
                            <option value="" disabled selected>STATUS</option>
                            <option value="On Time">ON TIME</option>
                            <option value="Delayed">DELAYED</option>
                            <option value="Cancelled">CANCELLED</option>
                        </select>
                        <div id="delay_box" class="flex gap-2">
                            <input type="number" placeholder="DAYS" class="form-input w-1/2">
                            <input type="number" placeholder="HRS" class="form-input w-1/2">
                        </div>
                    </div>
                    <div class="flex-grow"></div>
                    <div class="status-msg">STATE OVERRIDDEN</div>
                    <button type="submit" class="tab-btn tab-btn-mustard">Push Update</button>
                </form>
            </div>
        </div>

        <div class="history-tab border-sky-glow overflow-hidden" style="min-height: auto;">
            <div class="p-6 bg-slate-900 text-white flex justify-between items-center">
                <h3 class="text-xs font-black uppercase tracking-[0.2em]">Global Manifest History</h3>
                <div class="w-2 h-2 bg-[var(--mustard)] rounded-full animate-ping"></div>
            </div>
            <div class="manifest-scroll">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 border-b sticky top-0">
                        <tr>
                            <th class="p-5 text-[var(--sky-bright)] text-[10px] uppercase font-black">Passenger Identity</th>
                            <th class="p-5 text-[var(--sky-bright)] text-[10px] uppercase font-black">Flight ID</th>
                            <th class="p-5 text-[var(--sky-bright)] text-[10px] uppercase font-black">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php
                        $hist = $conn->query("SELECT p.first_name, p.last_name, fs.flight_no, fi.status FROM booking b JOIN passengers p ON b.passenger_id = p.passenger_id JOIN aircraft a ON b.tail_no = a.tail_no JOIN has_route hr ON a.tail_no = hr.tail_no JOIN route r ON hr.route_id = r.route_id JOIN flight_schedule fs ON r.route_id = fs.route_id JOIN flight_instance fi ON fs.flight_no = fi.flight_no");
                        if($hist) {
                            while($r = $hist->fetch_assoc()):
                                $cl = ($r['status'] == 'On Time') ? 'status-on-time' : (($r['status'] == 'Cancelled') ? 'status-cancelled' : 'status-delayed');
                        ?>
                        <tr class="hover:bg-sky-50 transition">
                            <td class="p-5 font-bold text-slate-700"><?php echo $r['first_name']." ".$r['last_name']; ?></td>
                            <td class="p-5 font-mono text-[var(--mustard)] font-bold"><?php echo $r['flight_no']; ?></td>
                            <td class="p-5"><span class="badge <?php echo $cl; ?>"><?php echo $r['status']; ?></span></td>
                        </tr>
                        <?php endwhile; } ?>
                    </tbody>
                </table>
            </div>
            <div class="p-8 bg-slate-50 border-t flex justify-center">
                <button class="tab-btn tab-btn-blue max-w-sm">Synchronize Logs</button>
            </div>
        </div>
    </main>

    <script>
        setInterval(() => { document.getElementById('live-clock').innerText = new Date().toLocaleTimeString('en-GB'); }, 1000);

        const updateFieldColor = (el) => {
            if (el.value && el.value !== "") el.classList.add('filled');
            else el.classList.remove('filled');
        };
        document.querySelectorAll('.form-input').forEach(el => {
            el.addEventListener('input', () => updateFieldColor(el));
            el.addEventListener('change', () => updateFieldColor(el));
        });

        document.getElementById('st_select').addEventListener('change', function() {
            document.getElementById('delay_box').style.display = (this.value === 'Delayed') ? 'flex' : 'none';
        });

        document.querySelectorAll('.admin-form').forEach(f => {
            f.addEventListener('submit', function(e) {
                e.preventDefault(); 
                const m = this.querySelector('.status-msg');
                m.innerText = this.getAttribute('data-msg');
                gsap.to(m, { opacity: 1, y: -5, duration: 0.4 });
                setTimeout(() => { gsap.to(m, { opacity: 0, y: 0, duration: 0.4 }); }, 2000);
            });
        });

        window.onload = () => {
            const tl = gsap.timeline({defaults: {ease: "power4.out", duration: 1.2}});
            tl.to(".sidebar", { x: 0 })
              .from("#header", { opacity: 0, y: -20 }, "-=0.8")
              .to(".command-tab", { 
                  opacity: 1, 
                  y: 0, 
                  stagger: 0.15, 
                  onComplete: function() { gsap.set(".command-tab", {clearProps: "y"}); } 
              }, "-=0.6")
              .to(".history-tab", { 
                  opacity: 1, 
                  y: 0, 
                  onComplete: function() { gsap.set(".history-tab", {clearProps: "y"}); } 
              }, "-=0.8");
        };
    </script>
</body>
</html>