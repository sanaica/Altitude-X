### Project Overview

The **Altitude X Airline Reservation System** is a full-stack, dynamic web application designed to simulate the real-world operations of a commercial airline. Built with a focus on robust database management and user experience, the system features Role-Based Access Control (RBAC) to provide specialized interfaces for Passengers, Administrators, and Airline Crew.

### Technology Stack

* **Backend:** PHP (running on XAMPP)
* **Database:** MySQL (Relational Database Management System)
* **Frontend Design:** HTML5, Tailwind CSS (for modern, responsive styling)
* **Frontend Interactions:** JavaScript, GSAP (GreenSock Animation Platform for smooth UI transitions)

### Core Modules & Features

**1. The Passenger Portal (Booking Engine)**

* **Smart Search:** Passengers can search for flights by selecting their departure and arrival hubs. The UI includes dynamic logic to prevent selecting the same city for both origin and destination.
* **Real-Time Availability:** The system queries the database to only display flights that are actively scheduled for future dates, hiding past or cancelled flights.
* **Automated Ticketing:** Upon confirming a booking and entering passenger details, the system automatically generates a formatted, printable digital Boarding Pass complete with a randomized seat assignment, flight times, and a generated barcode.
* **Smart Passenger Recognition:** The database recognizes returning passengers via their email address, linking new bookings to their existing profile to prevent data duplication.

**2. The Admin Control Center (Flight Deck)**

* **Network Expansion:** Admins can add new airports (Terminals) to the database to expand the airline's operational network.
* **Master Scheduling & Deployment:** Admins create core flight routes (e.g., BOM to DXB) and then "deploy" them by opening up specific dates for passengers to book.
* **Dynamic Operations & Cascading Updates:** Admins can change a live flight's status to "Delayed" (which automatically recalculates and updates the database timestamps) or "Cancelled" (which triggers a cascading update to automatically change the payment status of all affected passengers to "Refunded").
* **Live Manifest:** A real-time tracking board that displays all passenger bookings, payment statuses, and flight conditions.

**3. The Crew Logistics Panel**

* **Personnel Onboarding:** A system to register new employees (Pilots and Cabin Crew). It dynamically adjusts required fields (like demanding an Aviation License Number only if "Pilot" is selected) and automatically generates system login credentials for the new staff.
* **Mission Assignment:** A tool to link specific crew members to active aircraft and live flight routes using junction tables.
* **Global Roster:** A live tracking table showing the current operational status of the workforce, displaying exactly which aircraft and flight each crew member is assigned to.

### Database Design Highlights (DBMS Focus)

* **Data Integrity:** Utilizes strict Primary and Foreign Keys to maintain relationships between Users, Passengers, Bookings, Routes, and Aircraft.
* **Complex Relationships:** Handles Many-to-Many (M:N) relationships efficiently using junction tables (e.g., the `operated_by` table linking Crew to Aircraft, and `has_route` linking Aircraft to Routes).
* **Advanced SQL Queries:** Relies on complex `JOIN` operations to fetch human-readable data across multiple tables (e.g., displaying the aircraft model, route, and departure time in a single dropdown menu).

Would you like me to format this into a structured `README.md` file for your code repository, or help you draft the slide content for your presentation?
