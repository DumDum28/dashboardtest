<?php
$host = 'localhost';
$user = 'root';
$password = '1234';
$database = 'intpro';

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get age range from GET parameters
$min_age = isset($_GET['min_age']) ? (int)$_GET['min_age'] : 0;
$max_age = isset($_GET['max_age']) ? (int)$_GET['max_age'] : 100;
$hospital_filter = isset($_GET['hospital_filter']) ? $_GET['hospital_filter'] : ''; //เพิ่มใหม่

// Create where clause for age range
$where_clause = "WHERE report_patient_age BETWEEN $min_age AND $max_age";

//เพิ่มใหม่
if (!empty($hospital_filter) && $hospital_filter !== 'ทั้งหมด') {
    $where_clause .= " AND hospital_waypoint = '" . $conn->real_escape_string($hospital_filter) . "'";
}

$sql = "SELECT 
    report_reason,
    SUM(CASE WHEN report_patient_gender = 'ชาย' THEN 1 ELSE 0 END) as male_count,
    SUM(CASE WHEN report_patient_gender = 'หญิง' THEN 1 ELSE 0 END) as female_count
    FROM emergency_case 
    $where_clause
    GROUP BY report_reason";

$result = $conn->query($sql);

$labels = [];
$maleData = [];
$femaleData = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $labels[] = $row['report_reason'];
        $maleData[] = $row['male_count'];
        $femaleData[] = $row['female_count'];
    }
}

//เพิ่มใหม่
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode([
        "labels" => $labels,
        "maleData" => $maleData,
        "femaleData" => $femaleData
    ]);
    exit;
}

//เพิ่มใหม่
if (isset($_GET['get_hospitals'])) {
    $hospitalQuery = "SELECT DISTINCT hospital_waypoint FROM emergency_case WHERE hospital_waypoint IS NOT NULL AND hospital_waypoint != ''";
    $hospitalResult = $conn->query($hospitalQuery);

    $hospitals = [];
    while ($row = $hospitalResult->fetch_assoc()) {
        $hospitals[] = $row['hospital_waypoint'];
    }

    header('Content-Type: application/json');
    echo json_encode($hospitals);
    exit;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="path/to/font-awesome/css/font-awesome.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Itim&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/style.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/index.js"></script>
    <title>ดูรายงานเคส</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        canvas {
            width: 80% !important;
            height: 60% !important;
            max-width: 800px;
            max-height: 600px;
            margin: auto;
            display: block;
        }

        .filter-container {
            text-align: center;
            margin: 20px 0;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
        }

        .age-input {
            width: 60px;
            padding: 8px;
            font-size: 16px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        button {
            padding: 8px 15px;
            font-size: 16px;
            border-radius: 4px;
            background-color: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
        }

        button:hover {
            background-color: #45a049;
        }
    </style>
</head>

<body>
    <header class="header">
        <div class="logo-section">
            <img src="img/logo.jpg" alt="" class="logo">
            <h1 href="ceo_home_page.html" style="font-family: Itim;">CEO - HOME</h1>
        </div>
        <nav class="nav" style="margin-left: 20%;">
            <a href="approve_page.html" class="nav-item">อนุมัติคำสั่งซื้อ/เช่า</a>
            <a href="approve_clam_page.html" class="nav-item">อนุมัติเคลม</a>
            <a href="summary_page.html" class="nav-item">สรุปยอดขาย</a>
            <a href="case_report_page.html" class="nav-item active">ดูสรุปรายงานเคส</a>
            <a href="history_fixed_page.html" class="nav-item">ประวัติการส่งซ่อมรถและอุปกรณ์การแพทย์</a>
            <a href="static_car_page.html" class="nav-item">สถิติการใช้งานรถ</a>
        </nav>
    </header>
    <h1 style="text-align: center;">ดูสรุปรายงานเคสฉุกเฉิน</h1>
    <main class="main-content">
        <div class="search-section">
            <div class="filter-icon">
                <i class="fa-solid fa-filter"></i> <!-- ไอคอน Filter -->
            </div>

            <div class="filter-sidebar" id="filterSidebar">
                <div class="sidebar-header">
                    <h2>ตัวกรอง</h2>
                    <button class="close-sidebar">&times;</button>
                </div>
                <div class="sidebar-content">
                    <label for="calendarSelect">เลือกวันที่:</label>
                    <input class="calendar-selected" id="calendarSelect" type="text" placeholder="เลือกวันที่" value="2025-01-22">


                    <label for="filter-gender">เพศ:</label>
                    <select id="filter-gender-list" class="filter-select">
                        <option value="" selected hidden>กรุณาเลือกเพศ</option>
                        <option value="ทั้งหมด" selected>ทั้งหมด</option>
                        <option value="ชาย">ชาย</option>
                        <option value="หญิง">หญิง</option>
                    </select>

                    <!-- แก้เป็น radio -->
                    <label>ช่วงอายุ:</label>
                    <input type="number" id="minAge" class="age-input" value="<?php echo $min_age; ?>" min="0" max="100">
                    <span>ถึง</span>
                    <input type="number" id="maxAge" class="age-input" value="<?php echo $max_age; ?>" min="0" max="100">
                    <span>ปี</span>
                    <br><br>

                    <label for="filter-symtom">สาเหตุ/อาการป่วย:</label>
                    <select id="filter-symtom-list" class="filter-select">
                        <option value="" selected hidden>กรุณาเลือก</option>
                        <option value="ทั้งหมด" selected>ทั้งหมด</option>
                        <option value="อุบัติเหตุ">อุบัติเหตุ</option>
                        <option value="อาการป่วย">อาการป่วย</option>
                        <option value="อื่นๆ">อื่นๆ</option>
                    </select>
                    
                    <!-- ลบจังหวัด -->
                    <label for="filter-hospital">โรงพยาบาล:</label>
                    <select id="filter-hospital-list" class="filter-select">
                        <option value="" selected hidden>กรุณาเลือกโรงพยาบาล</option>
                        <option value="0" selected>ทั้งหมด</option>
                        
                    </select>



                    <label for="filter-zone">เขตที่รับแจ้งเหตุ:</label>
                    <select id="filter-zone-list" class="filter-select">
                        <option value="" selected hidden>กรุณาเลือกเขต</option>
                        <option value="ทั้งหมด" selected>ทั้งหมด</option>
                        <option value="พระนคร">พระนคร</option>
                        <option value="ดุสิต">ดุสิต</option>
                        <option value="หนองจอก">หนองจอก</option>
                        <option value="บางรัก">บางรัก</option>
                        <option value="บางเขน">บางเขน</option>
                        <option value="บางกะปิ">บางกะปิ</option>
                        <option value="ปทุมวัน">ปทุมวัน</option>
                        <option value="ป้อมปราบศัตรูพ่าย">ป้อมปราบศัตรูพ่าย</option>
                        <option value="พระโขนง">พระโขนง</option>
                        <option value="มีนบุรี">มีนบุรี</option>
                        <option value="ลาดกระบัง">ลาดกระบัง</option>
                        <option value="ยานนาวา">ยานนาวา</option>
                        <option value="สัมพันธวงศ์">สัมพันธวงศ์</option>
                        <option value="พญาไท">พญาไท</option>
                        <option value="ธนบุรี">ธนบุรี</option>
                        <option value="บางกอกใหญ่">บางกอกใหญ่</option>
                        <option value="ห้วยขวาง">ห้วยขวาง</option>
                        <option value="คลองสาน">คลองสาน</option>
                        <option value="ตลิ่งชัน">ตลิ่งชัน</option>
                        <option value="บางกอกน้อย">บางกอกน้อย</option>
                        <option value="บางขุนเทียน">บางขุนเทียน</option>
                        <option value="ภาษีเจริญ">ภาษีเจริญ</option>
                        <option value="หนองแขม">หนองแขม</option>
                        <option value="ราษฎร์บูรณะ">ราษฎร์บูรณะ</option>
                        <option value="บางพลัด">บางพลัด</option>
                        <option value="ดินแดง">ดินแดง</option>
                        <option value="บึงกุ่ม">บึงกุ่ม</option>
                        <option value="สาทร">สาทร</option>
                        <option value="บางซื่อ">บางซื่อ</option>
                        <option value="จตุจักร">จตุจักร</option>
                        <option value="บางคอแหลม">บางคอแหลม</option>
                        <option value="ประเวศ">ประเวศ</option>
                        <option value="คลองเตย">คลองเตย</option>
                        <option value="สวนหลวง">สวนหลวง</option>
                        <option value="จอมทอง">จอมทอง</option>
                        <option value="ดอนเมือง">ดอนเมือง</option>
                        <option value="ราชเทวี">ราชเทวี</option>
                        <option value="ลาดพร้าว">ลาดพร้าว</option>
                        <option value="วัฒนา">วัฒนา</option>
                        <option value="บางแค">บางแค</option>
                        <option value="หลักสี่">หลักสี่</option>
                        <option value="สายไหม">สายไหม</option>
                        <option value="คันนายาว">คันนายาว</option>
                        <option value="สะพานสูง">สะพานสูง</option>
                        <option value="วังทองหลาง">วังทองหลาง</option>
                        <option value="คลองสามวา">คลองสามวา</option>
                        <option value="บางนา">บางนา</option>
                        <option value="ทวีวัฒนา">ทวีวัฒนา</option>
                        <option value="บางบอน">บางบอน</option>
                    </select>




                </div>
            </div>
        </div>

    </main>

    <canvas id="case"></canvas>

    <script>
        const labels = <?php echo json_encode($labels); ?>;
        const maleData = <?php echo json_encode($maleData); ?>;
        const femaleData = <?php echo json_encode($femaleData); ?>;

        const mychart = new Chart(document.getElementById("case"), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'ชาย',
                    data: maleData,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }, {
                    label: 'หญิง',
                    data: femaleData,
                    backgroundColor: 'rgba(255, 99, 132, 0.5)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    x: {
                        stacked: true,
                        title: {
                            display: true,
                            text: 'สาเหตุการแจ้งเหตุ'
                        }
                    },
                    y: {
                        stacked: true,
                        title: {
                            display: true,
                            text: 'จำนวนผู้ป่วย'
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'สถิติผู้ป่วยฉุกเฉินแยกตามสาเหตุและเพศ',
                        font: {
                            size: 18
                        }
                    },
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: {
                            boxWidth: 20,
                            padding: 15
                        }
                    }
                }
            }
        });
        document.getElementById('filter-gender-list').addEventListener('change', function() {
            const gender = this.value;

            if (gender === 'ชาย') {
                mychart.data.datasets[0].hidden = false;
                mychart.data.datasets[1].hidden = true;
            } else if (gender === 'หญิง') {
                mychart.data.datasets[0].hidden = true;
                mychart.data.datasets[1].hidden = false;
            } else {
                mychart.data.datasets[0].hidden = false;
                mychart.data.datasets[1].hidden = false;
            }
            mychart.update();
        });

        // สคริปต์สำหรับเปิด-ปิด Sidebar
        document.addEventListener("DOMContentLoaded", () => {
            const filterIcon = document.querySelector(".filter-icon");
            const sidebar = document.getElementById("filterSidebar");
            const closeSidebar = document.querySelector(".close-sidebar");

            // เปิด Sidebar
            filterIcon.addEventListener("click", () => {
                sidebar.classList.add("open");
            });

            // ปิด Sidebar
            closeSidebar.addEventListener("click", () => {
                sidebar.classList.remove("open");
            });

            // ปิด Sidebar เมื่อคลิกนอก Sidebar
            document.addEventListener("click", (e) => {
                if (!sidebar.contains(e.target) && !filterIcon.contains(e.target)) {
                    sidebar.classList.remove("open");
                }
            });

        });
        // // สคริปต์สำหรับแสดงค่าของ Slider
        // const slider = document.getElementById("priceRange");
        // const value = document.getElementById("value");

        // const updateValue = () => {
        //     value.textContent = slider.value;
        // }
        // slider.oninput = updateValue;
        // updateValue();
        // ตั้งค่าปฏิทิน Flatpickr
        flatpickr("#calendarSelect", {
            dateFormat: "Y-m-d", // รูปแบบวันที่เป็น YYYY-MM-DD
            onChange: function(selectedDates, dateStr, instance) {
                // เมื่อผู้ใช้เลือกวันที่, เรียกใช้งานฟังก์ชัน updateChart
                updateChart(dateStr);
            }
        });
        

        //********************* ตั้งแต่นี้เปลี่ยนใหม่ ******************************
        document.addEventListener("DOMContentLoaded", () => {
            const minAgeInput = document.getElementById('minAge');
            const maxAgeInput = document.getElementById('maxAge');
            let typingTimer; // ใช้สำหรับตรวจจับการพิมพ์

            function updateAgeRange() {
                clearTimeout(typingTimer); // ล้างตัวจับเวลาหากพิมพ์ต่อเนื่อง
                typingTimer = setTimeout(() => { // ตั้งเวลาให้รอ 1 วินาทีหลังจากหยุดพิมพ์
                    const minAge = minAgeInput.value;
                    const maxAge = maxAgeInput.value;

                    if (parseInt(minAge) > parseInt(maxAge)) {
                        alert('กรุณาระบุช่วงอายุให้ถูกต้อง');
                        return;
                    }

                    const params = new URLSearchParams(window.location.search);
                    params.set('min_age', minAge);
                    params.set('max_age', maxAge);

                    // ใช้ `history.pushState` เพื่อเปลี่ยน URL โดยไม่ต้องรีเฟรชหน้า
                    history.pushState(null, "", "?" + params.toString());

                    // ทำ AJAX รีโหลดเฉพาะข้อมูลใหม่โดยไม่รีหน้าเว็บ
                    reloadChartData(minAge, maxAge);
                }, 500); // หน่วงเวลา 1 วินาที
            }

            // ตรวจจับการเปลี่ยนแปลงค่าอายุและอัปเดตโดยอัตโนมัติ
            minAgeInput.addEventListener("input", updateAgeRange);
            maxAgeInput.addEventListener("input", updateAgeRange);
        });

        function reloadChartData(minAge, maxAge) {
            fetch(`chart.php?min_age=${minAge}&max_age=${maxAge}&ajax=1`)
                .then(response => response.json())
                .then(data => {
                    mychart.data.labels = data.labels;
                    mychart.data.datasets[0].data = data.maleData;
                    mychart.data.datasets[1].data = data.femaleData;
                    mychart.update();
                })
                .catch(error => console.error('Error fetching data:', error));
        }

        document.addEventListener("DOMContentLoaded", () => {
            const hospitalSelect = document.getElementById('filter-hospital-list');

            // โหลดรายชื่อโรงพยาบาล
            fetch('chart.php?get_hospitals=1')
                .then(response => response.json())
                .then(data => {
                    data.forEach(hospital => {
                        const option = document.createElement("option");
                        option.value = hospital;
                        option.textContent = hospital;
                        hospitalSelect.appendChild(option);
                    });
                })
                .catch(error => console.error('Error loading hospitals:', error));

            // ฟังก์ชันอัปเดตกราฟเมื่อเปลี่ยนฟิลเตอร์
            function updateChart() {
                const minAge = document.getElementById('minAge').value;
                const maxAge = document.getElementById('maxAge').value;
                const hospital = hospitalSelect.value;

                const params = new URLSearchParams(window.location.search);
                params.set('min_age', minAge);
                params.set('max_age', maxAge);
                params.set('hospital_filter', hospital);

                history.pushState(null, "", "?" + params.toString());

                fetch(`chart.php?${params.toString()}&ajax=1`)
                    .then(response => response.json())
                    .then(data => {
                        mychart.data.labels = data.labels;
                        mychart.data.datasets[0].data = data.maleData;
                        mychart.data.datasets[1].data = data.femaleData;
                        mychart.update();
                    })
                    .catch(error => console.error('Error fetching data:', error));
            }

            // ตรวจจับการเปลี่ยนค่าของฟิลเตอร์โรงพยาบาล
            hospitalSelect.addEventListener("change", updateChart);
        });
        //**************************** ถึงตรงนี้เลย *********************************
    </script>
</body>

</html>