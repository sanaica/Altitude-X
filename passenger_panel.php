<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'passenger') {
    header("Location: login.php"); 
    exit();
}
$conn = new mysqli("localhost", "root", "", "altitude_x");

// 1. Fetch Airports for Dropdowns
$airports = [];
$result = $conn->query("SELECT * FROM airports ORDER BY country, city");
while($row = $result->fetch_assoc()) {
    $group_name = $row['country'] . " (" . $row['city'] . ")";
    $airports[$group_name][] = $row;
}

// 2. Handle Final Booking Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_booking'])) {
    $fname = $conn->real_escape_string($_POST['first_name']);
    $lname = $conn->real_escape_string($_POST['last_name']);
    $email = $conn->real_escape_string($_POST['email']);
    $passport = $conn->real_escape_string($_POST['passport_no']);
    $phone = $conn->real_escape_string($_POST['phone_no']);
    $class = $_POST['class'];
    $instance_id = $_POST['selected_instance']; 
    $b_date = date('Y-m-d'); 
    
    // Get the tail_no linked to this flight
    $flight_info = $conn->query("
        SELECT fs.flight_no, hr.tail_no 
        FROM flight_instance fi
        JOIN flight_schedule fs ON fi.flight_no = fs.flight_no
        LEFT JOIN has_route hr ON fs.route_id = hr.route_id
        WHERE fi.instance_id = $instance_id LIMIT 1
    ")->fetch_assoc();
    
    $tail_no = $flight_info['tail_no'];

    // Fallback Airplane if route is brand new
    if (empty($tail_no)) {
        $fallback_aircraft = $conn->query("SELECT tail_no FROM aircraft LIMIT 1")->fetch_assoc();
        $tail_no = $fallback_aircraft['tail_no'];
    }

    // Smart Passenger Recognition
    $check_pass = $conn->query("SELECT passenger_id FROM passengers WHERE email = '$email'");
    if ($check_pass->num_rows > 0) {
        $p_data = $check_pass->fetch_assoc();
        $new_passenger_id = $p_data['passenger_id'];
    } else {
        $conn->query("INSERT INTO passengers (first_name, last_name, email, passport_no) VALUES ('$fname', '$lname', '$email', '$passport')");
        $new_passenger_id = $conn->insert_id; 
        $conn->query("INSERT INTO passenger_phone (passenger_id, phone_no) VALUES ($new_passenger_id, '$phone')");
    }

    // Insert Booking
    $book_sql = "INSERT INTO booking (b_date, class, payment_status, tail_no, passenger_id) 
                 VALUES ('$b_date', '$class', 'Paid', '$tail_no', $new_passenger_id)";
    
    if ($conn->query($book_sql) === TRUE) {
        $booking_id = $conn->insert_id;
        $booking_success = true;

        // FETCH ALL DETAILS FOR THE BOARDING PASS!
        $ticket_query = $conn->query("
            SELECT fs.flight_no, fs.departure, fs.arrival, fi.instance_date, r.source_code, r.destination_code
            FROM flight_instance fi
            JOIN flight_schedule fs ON fi.flight_no = fs.flight_no
            JOIN route r ON fs.route_id = r.route_id
            WHERE fi.instance_id = $instance_id
        ");
        $t_data = $ticket_query->fetch_assoc();

        // Generate a random seat
        $seat_letters = array("A", "B", "C", "D", "E", "F");
        $assigned_seat = rand(1, 30) . $seat_letters[rand(0, 5)];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Altitude X | Book Journey</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;800&family=Syncopate:wght@700&display=swap');
        
        :root { 
            --mustard: #EAB308; --sky: #0ea5e9; --navy: #0F172A; 
            --soft-grey: #94a3b8; --success-green: #22c55e; --mustard-light: #fefce8;
        }
        
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; color: var(--navy); margin: 0; overflow-x: hidden; }

        .sidebar { width: 260px; background: var(--navy); color: white; position: fixed; height: 100vh; z-index: 50; }
        .logo-pillar { width: 4px; height: 35px; background: var(--mustard); box-shadow: 0 0 15px var(--mustard); }
        .logo-x { font-family: 'Syncopate', sans-serif; font-size: 1.6rem; color: white; line-height: 1; }

        .booking-card { 
            background: #ffffff; border-radius: 2.5rem; border: 1px solid #f1f5f9;
            border-bottom: 8px solid var(--sky); box-shadow: 0 40px 80px -15px rgba(15, 23, 42, 0.08);
            max-width: 600px; opacity: 0; transform: translateY(30px);
        }
        .success-card { max-width: 800px; } /* Wider card for the ticket */

        .label-main { font-size: 0.7rem; font-weight: 800; color: #000000; text-transform: uppercase; letter-spacing: 0.2em; margin-bottom: 10px; display: block; }
        
        .form-select, .form-input { 
            background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 16px; 
            padding: 16px; width: 100%; outline: none; transition: all 0.3s; 
            font-weight: 600; color: var(--navy); font-size: 0.85rem; appearance: none;
        }
        
        .input-active { border-color: var(--mustard) !important; background-color: var(--mustard-light) !important; box-shadow: 0 4px 12px rgba(234, 179, 8, 0.1); }
        .form-select:focus, .form-input:focus { border-color: var(--sky); background: white; }

        .select-wrapper { position: relative; }
        .select-wrapper::after {
            content: '\f107'; font-family: 'Font Awesome 6 Free'; font-weight: 900;
            position: absolute; right: 20px; top: 50%; transform: translateY(-50%); color: var(--soft-grey); pointer-events: none;
        }

        .btn-search { background: var(--navy); color: white; font-weight: 800; padding: 20px; border-radius: 18px; width: 100%; border: none; text-transform: uppercase; letter-spacing: 0.3em; cursor: pointer; transition: 0.3s; font-size: 0.75rem; }
        .btn-search:hover { background: var(--sky); transform: translateY(-3px); }

        .flight-option { border: 2px solid #e2e8f0; border-radius: 16px; padding: 15px; margin-bottom: 10px; cursor: pointer; transition: 0.3s; }
        .flight-option:hover { border-color: var(--sky); background: #f0f9ff; }
        input[type="radio"]:checked + .flight-option { border-color: var(--mustard); background: var(--mustard-light); border-width: 3px;}
        
        .status-dot { height: 10px; width: 10px; background-color: var(--success-green); border-radius: 50%; display: inline-block; box-shadow: 0 0 12px 4px rgba(34, 197, 94, 0.4); }
        #titleContainer { opacity: 0; }

        /* =======================================
           PRINT MEDIA QUERIES (THE MAGIC TRICK)
           ======================================= */
        @media print {
            body { background: white !important; margin: 0; padding: 0; }
            /* Hide the sidebar, headers, and UI buttons */
            .sidebar, header, #titleContainer, .print-hidden { display: none !important; }
            /* Expand the main area to fill the paper */
            main { margin: 0 !important; padding: 20px !important; width: 100% !important; justify-content: flex-start !important; }
            
            /* Strip the card styling so just the ticket prints */
            #bookingCard { border: none !important; box-shadow: none !important; max-width: 100% !important; padding: 0 !important; transform: none !important; }
            
            /* Force the browser to print the background colors (Navy, Mustard, etc) */
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            
            .ticket-cutout { display: none !important; } /* Hide the decorative cutouts so it looks clean on paper */
            .ticket-wrapper { border: 2px solid #e2e8f0 !important; page-break-inside: avoid; }
        }
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
            <a href="#" class="flex items-center gap-4 text-white p-4 bg-white/5 rounded-2xl border-l-4 border-[var(--mustard)]">
                <i class="fas fa-plane-departure text-[var(--mustard)]"></i> Book Flight
            </a>
            <a href="logout.php" class="flex items-center gap-4 text-red-400 p-4 mt-auto">
                <i class="fas fa-power-off"></i> Sign Out
            </a>
        </nav>
    </aside>

    <main class="flex-1 ml-[260px] p-12 relative flex flex-col items-center justify-start min-h-screen">
        
        <header class="absolute top-12 right-12 flex items-center gap-4">
            <div class="text-right">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Global Terminal</p>
                <p class="text-[11px] font-bold text-slate-700 uppercase mt-1 flex items-center justify-end gap-3">
                    <span class="status-dot"></span> System Online
                </p>
            </div>
        </header>

        <div class="mt-12 mb-8 text-center" id="titleContainer">
            <h2 class="text-6xl font-black text-slate-900 tracking-tighter">Book Your <span class="text-[var(--mustard)] italic">Journey</span><span class="text-[var(--sky)]">.</span></h2>
            <p class="text-[var(--sky)] font-extrabold text-[10px] uppercase tracking-[0.6em] mt-3">Altitude X Elite Reservations</p>
        </div>

        <?php if(isset($booking_success)): ?>
            
            <div class="booking-card success-card p-10 w-full flex flex-col items-center" id="bookingCard">
                
                <div class="text-center print-hidden">
                    <i class="fas fa-check-circle text-5xl text-[var(--success-green)] mb-4"></i>
                    <h3 class="text-2xl font-black mb-1">Reservation Confirmed</h3>
                    <p class="text-slate-500 mb-6 text-sm">Your boarding pass is ready. Please present this at the terminal.</p>
                </div>

                <div class="ticket-wrapper w-full border-2 border-slate-200 rounded-3xl overflow-hidden shadow-xl bg-white flex flex-col md:flex-row relative mb-8">
                    
                    <div class="w-full md:w-2/3 p-8 border-b-2 md:border-b-0 md:border-r-2 border-dashed border-slate-300 relative bg-white">
                        
                        <div class="flex justify-between items-center mb-6">
                            <div>
                                <h1 class="logo-x !text-[var(--navy)] text-2xl">X<span class="text-[var(--sky)]">.</span></h1>
                                <p class="text-[8px] tracking-[0.3em] text-slate-400 font-bold uppercase mt-1">Boarding Pass</p>
                            </div>
                            <div class="text-right">
                                <p class="text-[9px] text-slate-400 uppercase tracking-widest font-bold">Cabin Class</p>
                                <p class="font-black text-[var(--mustard)] uppercase tracking-widest"><?php echo $class; ?></p>
                            </div>
                        </div>

                        <div class="mb-6">
                            <p class="text-[9px] text-slate-400 uppercase tracking-widest font-bold mb-1">Passenger Name</p>
                            <p class="font-black text-xl text-slate-800 uppercase"><?php echo $fname . " " . $lname; ?></p>
                        </div>

                        <div class="flex justify-between items-center mb-6 relative">
                            <div class="absolute left-1/2 top-[40%] transform -translate-x-1/2 w-3/4 flex items-center justify-between opacity-30">
                                <div class="h-0 border-b-2 border-dashed border-slate-400 w-full"></div>
                                <i class="fas fa-plane text-xl text-slate-500 mx-3"></i>
                                <div class="h-0 border-b-2 border-dashed border-slate-400 w-full"></div>
                            </div>

                            <div class="text-left z-10 bg-white pr-4">
                                <p class="font-black text-5xl text-[var(--sky)]"><?php echo $t_data['source_code']; ?></p>
                                <p class="text-xs text-slate-500 font-bold mt-2 uppercase tracking-widest">Dep: <?php echo date("H:i", strtotime($t_data['departure'])); ?></p>
                            </div>
                            <div class="text-right z-10 bg-white pl-4">
                                <p class="font-black text-5xl text-[var(--navy)]"><?php echo $t_data['destination_code']; ?></p>
                                <p class="text-xs text-slate-500 font-bold mt-2 uppercase tracking-widest">Arr: <?php echo date("H:i", strtotime($t_data['arrival'])); ?></p>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <div>
                                <p class="text-[9px] text-slate-400 uppercase tracking-widest font-bold">Flight No.</p>
                                <p class="font-bold text-slate-800"><?php echo $t_data['flight_no']; ?></p>
                            </div>
                            <div>
                                <p class="text-[9px] text-slate-400 uppercase tracking-widest font-bold">Date</p>
                                <p class="font-bold text-slate-800"><?php echo date("d M Y", strtotime($t_data['instance_date'])); ?></p>
                            </div>
                            <div>
                                <p class="text-[9px] text-slate-400 uppercase tracking-widest font-bold">Seat</p>
                                <p class="font-black text-lg text-[var(--sky)] leading-none"><?php echo $assigned_seat; ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="w-full md:w-1/3 p-8 bg-[var(--navy)] text-white flex flex-col justify-between">
                        <div>
                            <p class="text-[9px] text-slate-400 uppercase tracking-widest font-bold mb-1">Flight</p>
                            <p class="font-bold text-lg text-white mb-4"><?php echo $t_data['flight_no']; ?></p>

                            <p class="text-[9px] text-slate-400 uppercase tracking-widest font-bold mb-1">Seat</p>
                            <p class="font-black text-4xl text-[var(--mustard)] mb-5"><?php echo $assigned_seat; ?></p>
                            
                            <p class="text-[9px] text-slate-400 uppercase tracking-widest font-bold mb-1">Booking Ref</p>
                            <p class="font-mono text-sm text-slate-300">AX-<?php echo 10000 + $booking_id; ?></p>
                        </div>
                        
                        <div class="mt-6 w-full h-12 bg-white rounded flex items-center justify-around px-2 opacity-90 overflow-hidden">
                            <?php 
                            for($i=0; $i<35; $i++) { 
                                $w = rand(1, 4); 
                                echo "<div class='h-full bg-slate-900' style='width: {$w}px; margin-right: 1px;'></div>"; 
                            } 
                            ?>
                        </div>
                    </div>
                    
                    <div class="ticket-cutout absolute left-[-12px] top-1/2 transform -translate-y-1/2 w-6 h-6 bg-[#f8fafc] rounded-full hidden md:block"></div>
                    <div class="ticket-cutout absolute right-[33.33%] translate-x-1/2 top-[-12px] w-6 h-6 bg-[#f8fafc] rounded-full hidden md:block z-20"></div>
                    <div class="ticket-cutout absolute right-[33.33%] translate-x-1/2 bottom-[-12px] w-6 h-6 bg-[#f8fafc] rounded-full hidden md:block z-20"></div>
                </div>
                <div class="flex gap-4 w-full max-w-sm print-hidden">
                    <button onclick="window.print()" class="btn-search bg-[var(--sky)] flex-1"><i class="fas fa-print"></i> Print Ticket</button>
                    <a href="passenger_panel.php" class="btn-search bg-slate-200 !text-slate-700 hover:!bg-slate-300 flex-1 text-center no-underline">Book Another</a>
                </div>
            </div>
        
        <?php else: ?>

            <div class="booking-card p-10 w-full" id="bookingCard">
                <form method="POST" action="" class="space-y-6">
                    <div class="flex gap-4">
                        <div class="flex-1">
                            <label class="label-main">Departure Hub</label>
                            <div class="select-wrapper">
                                <select name="from_airport" id="from_airport" class="form-select dynamic-field" required>
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

                        <div class="flex-1">
                            <label class="label-main">Arrival Hub</label>
                            <div class="select-wrapper">
                                <select name="to_airport" id="to_airport" class="form-select dynamic-field" required>
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
                    </div>
                    
                    <button type="submit" name="search_route" class="btn-search">Search Flights <i class="fas fa-arrow-right"></i></button>
                </form>

                <?php if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['search_route'])): ?>
                    <div class="mt-8 pt-8 border-t border-slate-200" id="resultsArea">
                        <?php
                        $from = $_POST['from_airport'];
                        $to = $_POST['to_airport'];

                        $sql = "
                            SELECT fi.instance_id, fi.flight_no, fi.instance_date, fs.departure, fs.arrival
                            FROM route r
                            JOIN flight_schedule fs ON r.route_id = fs.route_id
                            JOIN flight_instance fi ON fs.flight_no = fi.flight_no
                            WHERE r.source_code = '$from' AND r.destination_code = '$to' 
                            AND fi.instance_date >= CURDATE() AND fi.status != 'Cancelled'
                            ORDER BY fi.instance_date ASC
                        ";
                        $available_flights = $conn->query($sql);

                        if ($available_flights && $available_flights->num_rows > 0): ?>
                            
                            <h3 class="label-main text-[var(--sky)]"><i class="fas fa-check-circle"></i> Available Flights Found</h3>
                            <p class="text-xs text-slate-500 mb-4">Select a departure time and enter your details to complete the booking.</p>
                            
                            <form method="POST" action="">
                                <div class="mb-6 max-h-60 overflow-y-auto pr-2">
                                    <?php while($flight = $available_flights->fetch_assoc()): 
                                        $dep_time = date("h:i A", strtotime($flight['departure']));
                                        $arr_time = date("h:i A", strtotime($flight['arrival']));
                                        $f_date = date("D, d M Y", strtotime($flight['instance_date']));
                                    ?>
                                        <label class="block relative">
                                            <input type="radio" name="selected_instance" value="<?php echo $flight['instance_id']; ?>" required class="hidden">
                                            <div class="flight-option flex justify-between items-center">
                                                <div>
                                                    <div class="font-black text-lg text-slate-800"><?php echo $dep_time; ?> <span class="text-sm font-normal text-slate-400 mx-2">➔</span> <?php echo $arr_time; ?></div>
                                                    <div class="text-xs font-bold text-[var(--sky)]"><?php echo $f_date; ?></div>
                                                </div>
                                                <div class="text-right">
                                                    <div class="text-xs font-bold text-slate-400 tracking-widest uppercase">Flight</div>
                                                    <div class="font-mono font-bold text-slate-700"><?php echo $flight['flight_no']; ?></div>
                                                </div>
                                            </div>
                                        </label>
                                    <?php endwhile; ?>
                                </div>

                                <h3 class="label-main mt-6">Passenger Details</h3>
                                <div class="grid grid-cols-2 gap-4 mb-4">
                                    <input type="text" name="first_name" placeholder="First Name" class="form-input" required>
                                    <input type="text" name="last_name" placeholder="Last Name" class="form-input" required>
                                    <input type="email" name="email" placeholder="Email Address" class="form-input col-span-2" required>
                                    <input type="text" name="passport_no" placeholder="Passport No." class="form-input" required>
                                    <input type="text" name="phone_no" placeholder="Phone No." class="form-input" required>
                                </div>
                                
                                <div class="mb-6">
                                    <select name="class" class="form-select" required>
                                        <option value="Economy">Economy Class</option>
                                        <option value="Business">Business Class (+$800)</option>
                                    </select>
                                </div>

                                <button type="submit" name="confirm_booking" class="btn-search bg-[var(--mustard)] text-black">Confirm Reservation <i class="fas fa-check"></i></button>
                            </form>

                        <?php else: ?>
                            <div class="bg-red-50 border-l-4 border-red-500 p-6 rounded-lg text-center">
                                <i class="fas fa-plane-slash text-3xl text-red-400 mb-3"></i>
                                <h3 class="font-bold text-red-800">Route Currently Unavailable</h3>
                                <p class="text-sm text-red-600 mt-1">We do not have any scheduled flights connecting these two hubs at this time. Please try different destinations.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div>
        <?php endif; ?>

    </main>

    <script>
        window.onload = () => {
            const tl = gsap.timeline({ defaults: { ease: "power4.out" }});
            tl.to("#titleContainer", { opacity: 1, y: 0, startAt: { y: -80 }, duration: 1.5, ease: "back.out(1.2)" })
              .to("#bookingCard", { opacity: 1, y: 0, duration: 1.2 }, "-=1"); 
            
            const results = document.getElementById('resultsArea');
            if(results) {
                gsap.fromTo(results, { opacity: 0, y: 20 }, { opacity: 1, y: 0, duration: 0.8, delay: 0.2 });
            }
        };

        document.querySelectorAll('.dynamic-field').forEach(el => {
            el.addEventListener('change', () => {
                if(el.value) el.classList.add('input-active');
            });
        });

        const fromSelect = document.getElementById('from_airport');
        const toSelect = document.getElementById('to_airport');

        function preventDuplicateHubs() {
            if(!fromSelect || !toSelect) return;
            const fromVal = fromSelect.value;
            const toVal = toSelect.value;

            Array.from(toSelect.options).forEach(opt => {
                if(opt.value && opt.value === fromVal) opt.disabled = true;
                else opt.disabled = false;
            });

            Array.from(fromSelect.options).forEach(opt => {
                if(opt.value && opt.value === toVal) opt.disabled = true;
                else opt.disabled = false;
            });
        }

        if(fromSelect && toSelect) {
            fromSelect.addEventListener('change', preventDuplicateHubs);
            toSelect.addEventListener('change', preventDuplicateHubs);
        }
    </script>
</body>
</html>