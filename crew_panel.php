<?php
session_start();
$conn = new mysqli("localhost", "root", "", "altitude_x");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'crew') {
    header("Location: login.php"); 
    exit();
}
$user_id = $_SESSION['user_id'];

// ==========================================
// FORM 1: REGISTER NEW PERSONNEL
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_crew'])) {
    $name = $conn->real_escape_string($_POST['e_name']);
    $salary = (int)$_POST['salary'];
    $hours = (int)$_POST['flight_hours'];
    $langs = $conn->real_escape_string($_POST['languages']);
    $training = $conn->real_escape_string($_POST['training_level']);
    
    $license = !empty($_POST['license_no']) ? "'" . $conn->real_escape_string($_POST['license_no']) . "'" : "NULL";

    $username = strtolower(str_replace(' ', '_', $name)) . rand(10, 99);
    $conn->query("INSERT INTO users (username, password, role) VALUES ('$username', 'pass123', 'crew')");
    $new_emp_id = $conn->insert_id; 

    $sql = "INSERT INTO crew (employee_id, e_name, salary, license_no, flight_hours, languages, training_level) 
            VALUES ($new_emp_id, '$name', $salary, $license, $hours, '$langs', '$training')";
    
    if ($conn->query($sql) === TRUE) {
        $msg_crew = "<div class='success-msg'>✅ $name successfully registered! (Login: $username / pass123)</div>";
    } else {
        $msg_crew = "<div class='error-msg'>❌ Error: " . $conn->error . "</div>";
    }
}

// ==========================================
// FORM 2: ASSIGN CREW TO ACTIVE FLIGHT AIRCRAFT
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['assign_crew'])) {
    $emp_id = $_POST['assign_emp_id'];
    $tail_no = $_POST['assign_tail_no'];

    // Insert into operated_by (Links Crew to the Aircraft flying that specific route)
    $sql = "INSERT INTO operated_by (employee_id, tail_no) VALUES ($emp_id, '$tail_no')";
    if ($conn->query($sql) === TRUE) {
        $msg_assign = "<div class='success-msg'>✅ Personnel successfully assigned to flight mission!</div>";
    } else {
        $msg_assign = "<div class='error-msg'>❌ Assignment Failed (May already be assigned to this aircraft)</div>";
    }
}

// ==========================================
// FETCH DROPDOWN DATA
// ==========================================
$all_crew = $conn->query("SELECT employee_id, e_name, training_level FROM crew ORDER BY e_name");

// SMART QUERY: Fetches Aircraft but displays the LIVE FLIGHT DETAILS attached to it!
$active_aircraft_sql = "
    SELECT a.tail_no, a.model, fi.flight_no, fi.instance_date, fi.instance_id
    FROM aircraft a
    JOIN has_route hr ON a.tail_no = hr.tail_no
    JOIN flight_schedule fs ON hr.route_id = fs.route_id
    JOIN flight_instance fi ON fs.flight_no = fi.flight_no
    WHERE fi.instance_date >= CURDATE() AND fi.status != 'Cancelled'
    ORDER BY fi.instance_date ASC
";
$active_aircraft = $conn->query($active_aircraft_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Altitude X | Crew Logistics</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;800&family=Syncopate:wght@700&display=swap');
        
        :root { 
            --pastel-sky: #bae6fd; --mustard: #EAB308; --mustard-border: #fde047; 
            --deep-navy: #0F172A; --sky-bright: #0ea5e9; --neutral-grey: #94a3b8; 
            --light-green: #dcfce7; --dark-green: #166534;
        }
        
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; color: var(--deep-navy); margin: 0; overflow-x: hidden; }

        .sidebar { width: 260px; background: var(--deep-navy); color: white; position: fixed; height: 100vh; z-index: 50; transform: translateX(-100%); }
        .logo-pillar { width: 4px; height: 35px; background: var(--mustard); box-shadow: 0 0 15px var(--mustard); }
        .logo-x { font-family: 'Syncopate', sans-serif; font-size: 1.6rem; color: white; line-height: 1; }

        .crew-card { 
            background: #ffffff; border-radius: 1.5rem; opacity: 0;
            transition: transform 0.3s ease-out, box-shadow 0.3s ease-out, border-color 0.3s ease-out;
            will-change: transform;
        }
        .card-blue { border: 2px solid var(--pastel-sky); border-bottom: 6px solid var(--sky-bright); }
        .card-blue:hover { border-color: var(--sky-bright); transform: translateY(-6px); box-shadow: 0 15px 30px rgba(14, 165, 233, 0.1); }
        
        .card-mustard { border: 2px solid var(--mustard-border); border-bottom: 6px solid var(--mustard); }
        .card-mustard:hover { border-color: var(--mustard); transform: translateY(-6px); box-shadow: 0 15px 30px rgba(234, 179, 8, 0.15); }

        .badge-confirmed { background: var(--light-green); color: var(--dark-green); padding: 6px 14px; border-radius: 8px; font-size: 10px; font-weight: 800; text-transform: uppercase; }
        
        .form-input { 
            background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 12px; 
            padding: 12px 16px; width: 100%; outline: none; transition: 0.3s; font-size: 0.85rem;
            color: var(--deep-navy); font-weight: 600; 
        }
        .form-input:focus { border-color: var(--sky-bright); background: white; }
        
        .btn-submit { 
            background: var(--deep-navy); color: white; font-weight: 800; padding: 14px; 
            border-radius: 12px; width: 100%; border: none; text-transform: uppercase; 
            letter-spacing: 0.1em; cursor: pointer; transition: 0.3s; font-size: 0.75rem; 
        }
        .btn-submit:hover { background: var(--sky-bright); transform: translateY(-2px); }

        .success-msg { background: #dcfce7; color: #166534; padding: 10px; border-radius: 8px; font-size: 12px; font-weight: bold; margin-bottom: 15px; }
        .error-msg { background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 8px; font-size: 12px; font-weight: bold; margin-bottom: 15px; }
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
                <i class="fas fa-users-gear text-[var(--mustard)]"></i> Crew Logistics
            </a>
            <a href="logout.php" class="flex items-center gap-4 text-red-400 p-4 hover:bg-red-400/10 rounded-2xl mt-auto transition">
                <i class="fas fa-power-off"></i> Sign Out
            </a>
        </nav>
    </aside>

    <main class="flex-1 ml-[260px] p-10">
        <header class="mb-10 flex justify-between items-center" id="header">
            <div>
                <h2 class="text-4xl font-black text-slate-900 tracking-tighter">Crew <span class="text-[var(--mustard)] italic">Logistics.</span></h2>
                <p class="text-[var(--sky-bright)] font-extrabold text-[10px] uppercase tracking-[0.4em] mt-2">Personnel & Fleet Assignments</p>
            </div>

            <div class="flex items-center gap-6">
                <div class="text-right hidden lg:block">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">System Status</p>
                    <div class="flex items-center justify-end gap-2">
                        <span class="text-xs font-bold text-slate-700">ONLINE</span>
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                        </span>
                    </div>
                </div>
            </div>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-10">
            
            <div class="crew-card card-blue p-8 flex flex-col h-full">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-sm font-black uppercase tracking-[0.1em] text-slate-800"><i class="fas fa-user-plus text-[var(--sky-bright)] mr-2"></i> Onboard Personnel</h3>
                </div>
                
                <?php if(isset($msg_crew)) echo $msg_crew; ?>
                
                <form method="POST" action="" class="space-y-4 flex-grow">
                    <div class="flex gap-4">
                        <div class="flex-1">
                            <label class="text-[10px] font-bold text-slate-500 uppercase ml-1">Full Name</label>
                            <input type="text" name="e_name" placeholder="e.g. Rahul Sharma" class="form-input mt-1" required>
                        </div>
                        <div class="flex-1">
                            <label class="text-[10px] font-bold text-slate-500 uppercase ml-1">Role Type</label>
                            <select id="role_select" class="form-input mt-1" required onchange="toggleLicense()">
                                <option value="cabin">Cabin Crew</option>
                                <option value="pilot">Pilot</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <div class="flex-1">
                            <label class="text-[10px] font-bold text-slate-500 uppercase ml-1">Training Rank</label>
                            <select name="training_level" class="form-input mt-1" required>
                                <option value="Junior">Junior</option>
                                <option value="Senior">Senior</option>
                                <option value="Lead Cabin Crew">Lead Cabin Crew</option>
                                <option value="First Officer">First Officer</option>
                                <option value="Captain">Captain</option>
                            </select>
                        </div>
                        <div class="flex-1">
                            <label class="text-[10px] font-bold text-slate-500 uppercase ml-1">Base Salary ($)</label>
                            <input type="number" name="salary" placeholder="50000" class="form-input mt-1" required>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <div class="flex-1">
                            <label class="text-[10px] font-bold text-slate-500 uppercase ml-1">Known Languages</label>
                            <input type="text" name="languages" placeholder="English, Hindi, French..." class="form-input mt-1" required>
                        </div>
                        <div class="w-1/3">
                            <label class="text-[10px] font-bold text-slate-500 uppercase ml-1">Prior Hours</label>
                            <input type="number" name="flight_hours" placeholder="0" value="0" class="form-input mt-1" required>
                        </div>
                    </div>

                    <div id="license_div" style="display: none;">
                        <label class="text-[10px] font-bold text-[var(--mustard)] uppercase ml-1">Aviation License Number (Pilots Only)</label>
                        <input type="text" name="license_no" id="license_input" placeholder="e.g. ATPL-99827" class="form-input mt-1 border-[var(--mustard-border)]">
                    </div>

                    <button type="submit" name="register_crew" class="btn-submit mt-6">Register into Database</button>
                </form>
            </div>

            <div class="flex flex-col gap-6 h-full">
                
                <div class="crew-card card-mustard p-6">
                    <h3 class="text-sm font-black uppercase tracking-[0.1em] text-slate-800 mb-4"><i class="fas fa-plane-circle-check text-[var(--mustard)] mr-2"></i> Mission Assignment</h3>
                    
                    <?php if(isset($msg_assign)) echo $msg_assign; ?>
                    
                    <form method="POST" action="" class="flex gap-3 items-end">
                        <div class="flex-1">
                            <label class="text-[10px] font-bold text-slate-500 uppercase ml-1">Crew Member</label>
                            <select name="assign_emp_id" class="form-input mt-1" required>
                                <option value="" disabled selected>Select Staff</option>
                                <?php while($c = $all_crew->fetch_assoc()) {
                                    echo "<option value='".$c['employee_id']."'>".$c['e_name']." (".$c['training_level'].")</option>";
                                } ?>
                            </select>
                        </div>
                        <div class="flex-1">
                            <label class="text-[10px] font-bold text-[var(--mustard)] uppercase ml-1">Target Live Flight</label>
                            <select name="assign_tail_no" class="form-input mt-1 border-[var(--mustard-border)]" required>
                                <?php 
                                if ($active_aircraft && $active_aircraft->num_rows > 0) {
                                    echo "<option value='' disabled selected>-- Choose Flight --</option>";
                                    while($a = $active_aircraft->fetch_assoc()) {
                                        // FORMAT: [Flight ID] on Date (Aircraft)
                                        echo "<option value='".$a['tail_no']."'>[ID: ".$a['flight_no']."] on ".$a['instance_date']." (Aircraft: ".$a['tail_no'].")</option>";
                                    }
                                } else {
                                    echo "<option value='' disabled selected>No Active Flights</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <button type="submit" name="assign_crew" class="btn-submit !w-auto !bg-[var(--mustard)] hover:!bg-yellow-600 text-black px-6">Assign</button>
                    </form>
                </div>

                <div class="crew-card card-blue overflow-hidden flex-grow flex flex-col">
                    <div class="p-5 bg-slate-900 text-white flex justify-between items-center">
                        <h3 class="text-xs font-black uppercase tracking-[0.2em]">Global Crew Roster</h3>
                        <i class="fas fa-clipboard-list text-[var(--mustard)]"></i>
                    </div>
                    <div class="overflow-y-auto max-h-[250px] bg-white">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 border-b sticky top-0 shadow-sm z-10">
                                <tr>
                                    <th class="p-4 text-[var(--sky-bright)] text-[10px] uppercase font-black">Personnel</th>
                                    <th class="p-4 text-[var(--sky-bright)] text-[10px] uppercase font-black">Flight Assignment</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php
                                // Upgraded Query to fetch the assigned aircraft AND the active flights it is scheduled for
                                $roster_sql = "
                                    SELECT DISTINCT c.e_name, ob.tail_no, fi.flight_no, fi.instance_date
                                    FROM operated_by ob
                                    JOIN crew c ON ob.employee_id = c.employee_id
                                    LEFT JOIN has_route hr ON ob.tail_no = hr.tail_no
                                    LEFT JOIN flight_schedule fs ON hr.route_id = fs.route_id
                                    LEFT JOIN flight_instance fi ON fs.flight_no = fi.flight_no AND fi.instance_date >= CURDATE()
                                    ORDER BY c.e_name
                                ";
                                $roster_res = $conn->query($roster_sql);

                                if ($roster_res && $roster_res->num_rows > 0) {
                                    while($row = $roster_res->fetch_assoc()) {
                                        echo "<tr class='hover:bg-slate-50 transition'>";
                                        echo "<td class='p-4 font-bold text-slate-700'>" . htmlspecialchars($row['e_name']) . "</td>";
                                        
                                        if(!empty($row['flight_no'])) {
                                            echo "<td class='p-4'><span class='font-mono text-[var(--mustard)] font-bold'>" . $row['flight_no'] . "</span> <span class='text-xs text-slate-500 font-medium'>on " . $row['instance_date'] . " (A/C " . $row['tail_no'] . ")</span></td>";
                                        } else {
                                            echo "<td class='p-4 text-xs text-slate-400 italic'>Assigned to Aircraft " . $row['tail_no'] . " (Standby)</td>";
                                        }
                                        
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='2' class='p-12 text-center text-slate-400 font-bold italic uppercase text-xs tracking-widest'>No Staff Assigned Yet</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <script>
        function toggleLicense() {
            const role = document.getElementById('role_select').value;
            const licenseDiv = document.getElementById('license_div');
            const licenseInput = document.getElementById('license_input');
            
            if (role === 'pilot') {
                licenseDiv.style.display = 'block';
                licenseInput.required = true;
            } else {
                licenseDiv.style.display = 'none';
                licenseInput.required = false;
                licenseInput.value = '';
            }
        }

        window.onload = () => {
            gsap.timeline({defaults: {ease: "expo.out", duration: 1.0}})
                .to(".sidebar", { x: 0 })
                .from("#header", { opacity: 0, y: -15 }, "-=0.7")
                .to(".crew-card", { 
                    opacity: 1, 
                    y: 0, 
                    stagger: 0.15, 
                    onComplete: function() { gsap.set(".crew-card", { clearProps: "y" }); }
                }, "-=0.5");
        };
    </script>
</body>
</html>