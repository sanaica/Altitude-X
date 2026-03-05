<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php"); 
    exit();
}
$conn = new mysqli("localhost", "root", "", "altitude_x");

// ==========================================
// FORM PROCESSING ENGINE (THE BACKEND)
// ==========================================

// 1. LINK HUB (Add Airport)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_terminal'])) {
    $code = strtoupper($conn->real_escape_string($_POST['airport_code']));
    $city = $conn->real_escape_string($_POST['city']);
    $name = $conn->real_escape_string($_POST['airport_name']);
    $country = $conn->real_escape_string($_POST['country']);

    $sql = "INSERT INTO airports (airport_code, airport_name, city, country) VALUES ('$code', '$name', '$city', '$country')";
    $conn->query($sql);
}

// 2. ROUTE MAP (Add Flight Master Schedule)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_route'])) {
    $flight_no = strtoupper($conn->real_escape_string($_POST['flight_no']));
    $source = $_POST['source_code'];
    $dest = $_POST['dest_code'];
    $departure = str_replace('T', ' ', $_POST['departure']);
    $arrival = str_replace('T', ' ', $_POST['arrival']);

    if ($source !== $dest) {
        $route_check = $conn->query("SELECT route_id FROM route WHERE source_code = '$source' AND destination_code = '$dest'");
        if ($route_check->num_rows > 0) {
            $r_data = $route_check->fetch_assoc();
            $route_id = $r_data['route_id'];
        } else {
            $dist = rand(500, 3000); 
            $conn->query("INSERT INTO route (distance, source_code, destination_code) VALUES ($dist, '$source', '$dest')");
            $route_id = $conn->insert_id;
        }
        $conn->query("INSERT INTO flight_schedule (flight_no, arrival, departure, route_id) VALUES ('$flight_no', '$arrival', '$departure', $route_id)");
    }
}

// 3. DEPLOYMENT (Open a Flight Date)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['deploy_flight'])) {
    $flight_no = $_POST['flight_no'];
    $date = $_POST['instance_date'];
    $conn->query("INSERT INTO flight_instance (flight_no, instance_date, status) VALUES ('$flight_no', '$date', 'On Time')");
}

// 4. OPERATIONS (Update Status & Delay)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_ops'])) {
    $instance_id = $_POST['instance_id'];
    $new_status = $_POST['new_status'];
    
    if ($new_status == 'Delayed') {
        $d_days = (int)$_POST['delay_days'];
        $d_hours = (int)$_POST['delay_hours'];
        $delay_str = "Delayed by {$d_days}D {$d_hours}H.";
        
        // Find flight number to shift master schedule
        $inst_info = $conn->query("SELECT flight_no FROM flight_instance WHERE instance_id = $instance_id")->fetch_assoc();
        $f_no = $inst_info['flight_no'];
        
        $conn->query("UPDATE flight_schedule SET departure = DATE_ADD(departure, INTERVAL $d_days DAY), arrival = DATE_ADD(arrival, INTERVAL $d_days DAY) WHERE flight_no = '$f_no'");
        $conn->query("UPDATE flight_schedule SET departure = DATE_ADD(departure, INTERVAL $d_hours HOUR), arrival = DATE_ADD(arrival, INTERVAL $d_hours HOUR) WHERE flight_no = '$f_no'");
        
        $new_date_query = $conn->query("SELECT DATE(departure) as new_d FROM flight_schedule WHERE flight_no = '$f_no'")->fetch_assoc();
        $shifted_date = $new_date_query['new_d'];
        
        $conn->query("UPDATE flight_instance SET status = 'Delayed', instance_date = '$shifted_date', remarks = '$delay_str' WHERE instance_id = $instance_id");
    } elseif ($new_status == 'Cancelled') {
        $conn->query("UPDATE flight_instance SET status = 'Cancelled', remarks = 'FLIGHT CANCELLED' WHERE instance_id = $instance_id");
        $inst_info = $conn->query("SELECT flight_no FROM flight_instance WHERE instance_id = $instance_id")->fetch_assoc();
        $f_no = $inst_info['flight_no'];
        $conn->query("UPDATE booking b JOIN aircraft a ON b.tail_no = a.tail_no JOIN has_route hr ON a.tail_no = hr.tail_no JOIN route r ON hr.route_id = r.route_id JOIN flight_schedule fs ON r.route_id = fs.route_id SET b.payment_status = 'Refunded' WHERE fs.flight_no = '$f_no'");
    } else {
        $conn->query("UPDATE flight_instance SET status = 'On Time', remarks = NULL WHERE instance_id = $instance_id");
    }
}

// ==========================================
// DATA FETCHING FOR DROPDOWNS
// ==========================================
$airports = $conn->query("SELECT airport_code, city FROM airports ORDER BY city");
$airport_list = "";
while($row = $airports->fetch_assoc()) {
    $airport_list .= "<option value='".$row['airport_code']."'>".$row['city']." (".$row['airport_code'].")</option>";
}

$schedules = $conn->query("SELECT flight_no FROM flight_schedule");
$schedule_options = "";
while($s = $schedules->fetch_assoc()) {
    $schedule_options .= "<option value='".$s['flight_no']."'>".$s['flight_no']."</option>";
}

$instances = $conn->query("SELECT instance_id, flight_no, instance_date, status FROM flight_instance WHERE instance_date >= CURDATE() ORDER BY instance_date ASC");
$instance_options = "";
while($i = $instances->fetch_assoc()) {
    $instance_options .= "<option value='".$i['instance_id']."'>[ID: ".$i['instance_id']."] ".$i['flight_no']." on ".$i['instance_date']."</option>";
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

        .command-tab:hover, .history-tab:hover { transform: translateY(-12px) !important; }

        .border-sky-glow { border: 2px solid #bae6fd; border-bottom: 6px solid var(--sky-bright); }
        .border-sky-glow:hover { border-color: var(--sky-bright); box-shadow: 0 0 25px rgba(14, 165, 233, 0.2), 0 25px 50px -12px rgba(15, 23, 42, 0.1); }

        .border-mustard-glow { border: 2px solid #fef08a; border-bottom: 6px solid var(--mustard); }
        .border-mustard-glow:hover { border-color: var(--mustard); box-shadow: 0 0 25px rgba(234, 179, 8, 0.2), 0 25px 50px -12px rgba(15, 23, 42, 0.1); }

        .form-input { 
            background: #f8fafc; border-radius: 12px; 
            padding: 12px 16px; width: 100%; outline: none; transition: 0.3s; font-size: 0.85rem;
            color: var(--neutral-grey); font-weight: 600; 
            border: 2px solid #e2e8f0; 
        }

        .border-sky-glow .form-input { border-color: #7dd3fc; } 
        .border-sky-glow .form-input:focus { border-color: var(--sky-bright); box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.15); color: #000; }

        .border-mustard-glow .form-input { border-color: #fde047; } 
        .border-mustard-glow .form-input:focus { border-color: var(--mustard); box-shadow: 0 0 0 4px rgba(234, 179, 8, 0.15); color: #000; }

        .form-input.filled { color: #000000 !important; }

        .tab-title { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.15em; color: var(--sky-bright); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px; }

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

        #delay_box { display: none; }
        .badge { padding: 6px 14px; border-radius: 8px; font-size: 10px; font-weight: 800; text-transform: uppercase; }
        .status-on-time { background: #dcfce7; color: #166534; }
        .status-delayed { background: #fef9c3; color: #854d0e; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        .status-refunded { background: #f1f5f9; color: #475569; }
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
                <form method="POST" class="flex flex-col h-full">
                    <div class="space-y-4">
                        <input type="text" name="airport_code" placeholder="ICAO CODE (e.g. LHR)" class="form-input" maxlength="10" required>
                        <input type="text" name="city" placeholder="CITY NAME" class="form-input" required>
                        <input type="text" name="airport_name" placeholder="FULL AIRPORT NAME" class="form-input" required>
                        <input type="text" name="country" placeholder="COUNTRY" class="form-input" required>
                    </div>
                    <div class="flex-grow"></div>
                    <button type="submit" name="add_terminal" class="tab-btn tab-btn-blue mt-4">Link Hub</button>
                </form>
            </div>

            <div class="command-tab p-6 border-sky-glow">
                <div class="tab-title"><i class="fas fa-route"></i> Route Map</div>
                <form method="POST" class="flex flex-col h-full">
                    <div class="space-y-4">
                        <input type="text" name="flight_no" placeholder="NEW FLIGHT ID (e.g. AX-500)" class="form-input" required>
                        <select name="source_code" class="form-input" required>
                            <option value="" disabled selected>ORIGIN</option>
                            <?php echo $airport_list; ?>
                        </select>
                        <select name="dest_code" class="form-input" required>
                            <option value="" disabled selected>DESTINATION</option>
                            <?php echo $airport_list; ?>
                        </select>
                        <input type="datetime-local" name="departure" class="form-input" title="Departure Time" required>
                        <input type="datetime-local" name="arrival" class="form-input" title="Arrival Time" required>
                    </div>
                    <div class="flex-grow"></div>
                    <button type="submit" name="add_route" class="tab-btn tab-btn-blue mt-4">Register Route</button>
                </form>
            </div>

            <div class="command-tab p-6 border-mustard-glow">
                <div class="tab-title text-[var(--mustard)]"><i class="fas fa-plane-departure"></i> Deployment</div>
                <form method="POST" class="flex flex-col h-full">
                    <div class="space-y-4">
                        <select name="flight_no" class="form-input" required>
                            <option value="" disabled selected>MASTER FLIGHT ID</option>
                            <?php echo $schedule_options; ?>
                        </select>
                        <input type="date" name="instance_date" min="<?php echo date('Y-m-d'); ?>" class="form-input" required>
                    </div>
                    <div class="flex-grow"></div>
                    <button type="submit" name="deploy_flight" class="tab-btn tab-btn-mustard mt-4">Go Live</button>
                </form>
            </div>

            <div class="command-tab p-6 border-mustard-glow">
                <div class="tab-title text-[var(--mustard)]"><i class="fas fa-sync-alt"></i> Operations</div>
                <form method="POST" class="flex flex-col h-full">
                    <div class="space-y-4">
                        <select name="instance_id" class="form-input" required>
                            <option value="" disabled selected>TARGET LIVE FLIGHT</option>
                            <?php echo $instance_options; ?>
                        </select>
                        <select name="new_status" id="st_select" class="form-input" required>
                            <option value="" disabled selected>UPDATE STATUS</option>
                            <option value="On Time">ON TIME</option>
                            <option value="Delayed">DELAYED</option>
                            <option value="Cancelled">CANCELLED</option>
                        </select>
                        <div id="delay_box" class="flex gap-2">
                            <input type="number" name="delay_days" placeholder="DAYS" class="form-input w-1/2" value="0" min="0">
                            <input type="number" name="delay_hours" placeholder="HRS" class="form-input w-1/2" value="0" min="0" max="23">
                        </div>
                    </div>
                    <div class="flex-grow"></div>
                    <button type="submit" name="update_ops" class="tab-btn tab-btn-mustard mt-4">Push Update</button>
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
                            <th class="p-5 text-[var(--sky-bright)] text-[10px] uppercase font-black">Flight Status</th>
                            <th class="p-5 text-[var(--sky-bright)] text-[10px] uppercase font-black">Payment</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php
                        $hist = $conn->query("SELECT p.first_name, p.last_name, fs.flight_no, fi.status, b.payment_status FROM booking b JOIN passengers p ON b.passenger_id = p.passenger_id JOIN aircraft a ON b.tail_no = a.tail_no JOIN has_route hr ON a.tail_no = hr.tail_no JOIN route r ON hr.route_id = r.route_id JOIN flight_schedule fs ON r.route_id = fs.route_id JOIN flight_instance fi ON fs.flight_no = fi.flight_no ORDER BY b.booking_id DESC LIMIT 30");
                        if($hist && $hist->num_rows > 0) {
                            while($r = $hist->fetch_assoc()):
                                $cl = ($r['status'] == 'On Time') ? 'status-on-time' : (($r['status'] == 'Cancelled') ? 'status-cancelled' : 'status-delayed');
                                $pay_cl = ($r['payment_status'] == 'Paid') ? 'status-on-time' : (($r['payment_status'] == 'Refunded') ? 'status-refunded' : 'status-delayed');
                        ?>
                        <tr class="hover:bg-sky-50 transition">
                            <td class="p-5 font-bold text-slate-700"><?php echo $r['first_name']." ".$r['last_name']; ?></td>
                            <td class="p-5 font-mono text-[var(--mustard)] font-bold"><?php echo $r['flight_no']; ?></td>
                            <td class="p-5"><span class="badge <?php echo $cl; ?>"><?php echo $r['status']; ?></span></td>
                            <td class="p-5"><span class="badge <?php echo $pay_cl; ?>"><?php echo $r['payment_status']; ?></span></td>
                        </tr>
                        <?php endwhile; } else { echo "<tr><td colspan='4' class='p-5 text-center text-slate-400'>No active manifest data.</td></tr>"; } ?>
                    </tbody>
                </table>
            </div>
            <div class="p-8 bg-slate-50 border-t flex justify-center">
                <button onclick="window.location.reload()" class="tab-btn tab-btn-blue max-w-sm">Synchronize Logs</button>
            </div>
        </div>
    </main>

    <script>
        // Live Clock
        setInterval(() => { document.getElementById('live-clock').innerText = new Date().toLocaleTimeString('en-GB'); }, 1000);

        // Input Styling
        const updateFieldColor = (el) => {
            if (el.value && el.value !== "") el.classList.add('filled');
            else el.classList.remove('filled');
        };
        document.querySelectorAll('.form-input').forEach(el => {
            el.addEventListener('input', () => updateFieldColor(el));
            el.addEventListener('change', () => updateFieldColor(el));
        });

        // Toggle Delay Box
        document.getElementById('st_select').addEventListener('change', function() {
            document.getElementById('delay_box').style.display = (this.value === 'Delayed') ? 'flex' : 'none';
        });

        // GSAP Intro Animations
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