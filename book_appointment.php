<?php
session_start();
require 'db.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Only patients can access (case-insensitive safe)
if (!isset($_SESSION['user_id']) || strtolower(trim($_SESSION['role'] ?? '')) !== 'patient') {
    header('Location: login.php');
    exit;
}

$patientId   = (int)$_SESSION['user_id'];
$patientName = $_SESSION['name'] ?? 'Patient';

$error   = null;
$success = null;

// PRG success
if (isset($_GET['success'])) {
    $success = 'Your appointment has been created successfully. Waiting for doctor approval.';
}

/* -------------------- POST VALUES (re-populate) -------------------- */
$city       = $_POST['city']       ?? '';
$district   = $_POST['district']   ?? '';
$hospital   = $_POST['hospital']   ?? '';
$department = $_POST['department'] ?? '';
$doctorId   = $_POST['doctor_id']  ?? '';
$date       = $_POST['date']       ?? '';
$time       = $_POST['time']       ?? '';

/* -------------------- ŞEHİR LİSTESİ (81 İL) -------------------- */
$cities = [
    'Adana','Adıyaman','Afyonkarahisar','Ağrı','Amasya','Ankara','Antalya','Artvin','Aydın',
    'Balıkesir','Bilecik','Bingöl','Bitlis','Bolu','Burdur','Bursa','Çanakkale','Çankırı',
    'Çorum','Denizli','Diyarbakır','Edirne','Elazığ','Erzincan','Erzurum','Eskişehir',
    'Gaziantep','Giresun','Gümüşhane','Hakkâri','Hatay','Isparta','Mersin','İstanbul',
    'İzmir','Kars','Kastamonu','Kayseri','Kırklareli','Kırşehir','Kocaeli','Konya',
    'Kütahya','Malatya','Manisa','Kahramanmaraş','Mardin','Muğla','Muş','Nevşehir','Niğde',
    'Ordu','Rize','Sakarya','Samsun','Siirt','Sinop','Sivas','Tekirdağ','Tokat','Trabzon',
    'Tunceli','Şanlıurfa','Uşak','Van','Yozgat','Zonguldak','Aksaray','Bayburt','Karaman',
    'Kırıkkale','Batman','Şırnak','Bartın','Ardahan','Iğdır','Yalova','Karabük','Kilis',
    'Osmaniye','Düzce'
];

/* -------------------- BÖLÜMLER (SABİT) -------------------- */
$departments = [
    'Cardiology',
    'Neurology',
    'Internal Medicine',
    'Ophthalmology',
    'Dermatology',
    'Orthopedics',
    'Pediatrics',
    'General Surgery',
    'Psychiatry',
    'Gynecology and Obstetrics'
];

/* -------------------- DOKTORLAR DB'DEN -------------------- */
$doctorStmt = $conn->prepare("
    SELECT id, name, hospital_name, department
    FROM users
    WHERE LOWER(TRIM(role)) = 'doctor'
      AND hospital_name IS NOT NULL AND TRIM(hospital_name) <> ''
      AND department IS NOT NULL AND TRIM(department) <> ''
    ORDER BY name
");
$doctorStmt->execute();
$doctors = $doctorStmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------- SAAT LİSTESİ -------------------- */
$timeSlots = [
    '08:00','08:30','09:00','09:30',
    '10:00','10:30','11:00','11:30',
    '13:00','13:30','14:00','14:30',
    '15:00','15:30','16:00','16:30'
];

/* -------------------- PHP normalize (backend doğrulama için) -------------------- */
function norm_tr($s){
    $s = trim(mb_strtolower((string)$s, 'UTF-8'));
    $map = ['ç'=>'c','ğ'=>'g','ı'=>'i','ö'=>'o','ş'=>'s','ü'=>'u','İ'=>'i'];
    $s = strtr($s, $map);
    $s = preg_replace('/\s+/', ' ', $s);
    return $s;
}

/* -------------------- FORM SUBMIT -------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!$city || !$district || !$hospital || !$department || !$doctorId || !$date || !$time) {
        $error = 'Please fill in all fields.';
    } else {

        // 1) Backend doğrulama: doktor seçilen hastane + bölümde mi?
        $docCheck = $conn->prepare("
            SELECT id, hospital_name, department
            FROM users
            WHERE id = :id AND LOWER(TRIM(role))='doctor'
            LIMIT 1
        ");
        $docCheck->execute([':id' => (int)$doctorId]);
        $docRow = $docCheck->fetch(PDO::FETCH_ASSOC);

        if (!$docRow) {
            $error = 'Invalid doctor selected.';
        } else {
            $dbHosp = $docRow['hospital_name'] ?? '';
            $dbDept = $docRow['department'] ?? '';

            if (norm_tr($dbHosp) !== norm_tr($hospital) || norm_tr($dbDept) !== norm_tr($department)) {
                $error = 'Selected doctor does not work in the chosen hospital/department.';
            }
        }

        // 2) Slot dolu mu? (cancelled hariç, case-insensitive)
        if (!$error) {
            $checkSql = "
                SELECT COUNT(*) AS cnt
                FROM appointments
                WHERE doctor_id = :doc
                  AND appointment_date = :adate
                  AND appointment_time = :atime
                  AND LOWER(status) <> 'cancelled'
            ";
            $stmt = $conn->prepare($checkSql);
            $stmt->execute([
                ':doc'   => (int)$doctorId,
                ':adate' => $date,
                ':atime' => $time
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row && (int)$row['cnt'] > 0) {
                $error = 'This time slot is already booked for the selected doctor.';
            }
        }

        // 3) Insert (status = pending)
        if (!$error) {
            $insertSql = "
                INSERT INTO appointments
                    (patient_id, doctor_id, hospital_name, department, appointment_date, appointment_time, status)
                VALUES
                    (:pid, :doc, :hosp, :dept, :adate, :atime, 'pending')
            ";
            $stmt = $conn->prepare($insertSql);
            $stmt->execute([
                ':pid'   => $patientId,
                ':doc'   => (int)$doctorId,
                ':hosp'  => $hospital,
                ':dept'  => $department,
                ':adate' => $date,
                ':atime' => $time
            ]);

            // ✅ PRG redirect (refresh'te tekrar insert olmasın)
            header("Location: book_appointment.php?success=1");
            exit;
        }
    }
}

$backFallback = 'patient_dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Book Appointment</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root{
            --primary:#2563eb;
            --primary-soft:#e3f2fd;
            --card-radius:18px;
            --shadow-soft:0 14px 30px rgba(15,23,42,0.12);
        }
        *{ box-sizing:border-box; }
        body{
            margin:0; padding:0;
            font-family:'Inter',system-ui,-apple-system,'Segoe UI',sans-serif;
            background:radial-gradient(circle at top left,#e3f2fd 0,#f4f6f9 40%,#f4f6f9 100%);
            color:#111827;
        }

        .navbar {
            background:#ffffffaa;
            backdrop-filter: blur(10px);
            border-bottom:1px solid rgba(148,163,184,0.35);
            padding: 10px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position:sticky; top:0; z-index:20;
        }
        .navbar-left { font-weight: 700; font-size: 18px; color:#0f172a; }
        .navbar-right{
            font-size:14px;
            display:flex;
            align-items:center;
            gap:10px;
            flex-wrap:wrap;
            justify-content:flex-end;
        }

        .nav-btn{
            display:inline-flex;
            align-items:center;
            gap:8px;
            padding:8px 12px;
            border-radius:999px;
            text-decoration:none;
            font-weight:700;
            border:1px solid rgba(37,99,235,0.25);
            background: rgba(37,99,235,0.08);
            color:#2563eb;
            transition: transform .12s ease, filter .12s ease;
        }
        .nav-btn:hover{ transform: translateY(-1px); filter:brightness(1.02); }
        .nav-btn.logout{
            border:1px solid rgba(220,38,38,0.25);
            background: rgba(220,38,38,0.08);
            color:#dc2626;
        }

        .page{ max-width:1200px; margin:32px auto 50px; padding:0 18px 30px; }
        .appointment-card{
            background:#fff; border-radius:var(--card-radius);
            box-shadow:var(--shadow-soft);
            border:1px solid rgba(148,163,184,0.25);
            padding:28px 34px 30px;
        }
        .appointment-title{
            font-size:28px; margin:0 0 6px;
            display:flex; align-items:center; gap:10px;
        }
        .appointment-title span.icon{
            width:32px;height:32px;border-radius:999px;
            background:var(--primary-soft);
            display:inline-flex;align-items:center;justify-content:center;
        }
        .appointment-sub{ margin:0; font-size:14px; color:#6b7280; }

        .form-row{ margin-bottom:18px; }
        .form-row-inline{ display:grid; grid-template-columns:1.1fr 0.9fr; gap:18px; }
        label{ display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:5px; }

        select, input[type="date"]{
            width:100%; border-radius:999px; border:1px solid #d1d5db;
            padding:10px 16px; font-size:14px; background:#f9fafb;
        }
        select:disabled{ opacity:.6; cursor:not-allowed; }

        .btn-submit{
            margin-top:10px; padding:11px 26px; font-size:15px; font-weight:700;
            border-radius:999px; border:none;
            background:linear-gradient(135deg,#2563eb,#1d4ed8);
            color:#fff; cursor:pointer;
            box-shadow:0 10px 24px rgba(37,99,235,0.45);
        }

        .helper-text{ font-size:12px; color:#9ca3af; margin-top:2px; }

        .alert{
            border-radius:999px;
            padding:9px 18px;
            font-size:13px;
            margin-bottom:16px;
        }
        .alert-error{ background:rgba(248,113,113,0.08); border:1px solid rgba(248,113,113,0.7); color:#b91c1c; }
        .alert-success{ background:rgba(22,163,74,0.08); border:1px solid rgba(22,163,74,0.7); color:#166534; }

        @media (max-width:768px){
            .appointment-card{ padding:22px 18px 24px; }
            .form-row-inline{ grid-template-columns:1fr; }
            .appointment-title{ font-size:24px; }
        }
    </style>
</head>
<body>

<div class="navbar">
    <div class="navbar-left">Healthcare Record System</div>
    <div class="navbar-right">
        <span><?php echo h($patientName); ?> (Patient)</span>
        <a class="nav-btn"
           href="<?php echo h($backFallback); ?>"
           onclick="if (window.history.length > 1) { window.history.back(); return false; }">
            ← Back
        </a>
        <a class="nav-btn logout" href="logout.php">Log out</a>
    </div>
</div>

<div class="page">
    <div class="appointment-card">
        <h1 class="appointment-title"><span class="icon">📅</span>Book Appointment</h1>
        <p class="appointment-sub">This will be created as <b>pending</b>. Doctor must approve it.</p>

        <?php if ($error): ?><div class="alert alert-error"><?php echo h($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?php echo h($success); ?></div><?php endif; ?>

        <form method="post" action="">
            <div class="form-row">
                <label for="city">City</label>
                <select id="city" name="city">
                    <option value="">-- Select City --</option>
                    <?php foreach ($cities as $c): ?>
                        <option value="<?php echo h($c); ?>" <?php echo ($city === $c) ? 'selected' : ''; ?>>
                            <?php echo h($c); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <label for="district">District</label>
                <select id="district" name="district">
                    <option value="">-- Select District --</option>
                </select>
            </div>

            <div class="form-row">
                <label for="hospital">Hospital</label>
                <select id="hospital" name="hospital">
                    <option value="">-- Select Hospital --</option>
                </select>
            </div>

            <div class="form-row">
                <label for="department">Department / Clinic</label>
                <select id="department" name="department">
                    <option value="">-- Select Department --</option>
                    <?php foreach ($departments as $dep): ?>
                        <option value="<?php echo h($dep); ?>" <?php echo ($department === $dep) ? 'selected' : ''; ?>>
                            <?php echo h($dep); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <label for="doctor">Doctor</label>
                <select id="doctor" name="doctor_id" disabled>
                    <option value="">-- Select Doctor --</option>
                </select>
                <div class="helper-text" id="doctorHelp">Select hospital and department to see doctors.</div>
            </div>

            <div class="form-row form-row-inline">
                <div>
                    <label for="date">Date</label>
                    <input type="date" id="date" name="date" value="<?php echo h($date); ?>">
                    <div class="helper-text">Choose a suitable day.</div>
                </div>
                <div>
                    <label for="time">Time</label>
                    <select id="time" name="time">
                        <option value="">-- Select Time --</option>
                        <?php foreach ($timeSlots as $t): ?>
                            <option value="<?php echo h($t); ?>" <?php echo ($time === $t) ? 'selected' : ''; ?>>
                                <?php echo h($t); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="helper-text">Pick one slot.</div>
                </div>
            </div>

            <button type="submit" class="btn-submit">Book Appointment</button>
        </form>
    </div>
</div>

<script>
const DOCTORS = <?php echo json_encode($doctors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

const INITIAL = {
  city:       <?php echo json_encode($city, JSON_UNESCAPED_UNICODE); ?>,
  district:   <?php echo json_encode($district, JSON_UNESCAPED_UNICODE); ?>,
  hospital:   <?php echo json_encode($hospital, JSON_UNESCAPED_UNICODE); ?>,
  department: <?php echo json_encode($department, JSON_UNESCAPED_UNICODE); ?>,
  doctorId:   <?php echo json_encode($doctorId, JSON_UNESCAPED_UNICODE); ?>
};

function norm(s){
  return String(s || '')
    .trim()
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/\s+/g, ' ');
}

const citySelect       = document.getElementById('city');
const districtSelect   = document.getElementById('district');
const hospitalSelect   = document.getElementById('hospital');
const departmentSelect = document.getElementById('department');
const doctorSelect     = document.getElementById('doctor');
const doctorHelp       = document.getElementById('doctorHelp');

// Districts
const districtsByCity = {
  'Ankara':   ['Çankaya','Keçiören','Yenimahalle','Mamak','Etimesgut','Sincan','Polatlı','Gölbaşı'],
  'İstanbul': ['Kadıköy','Üsküdar','Beşiktaş','Şişli','Bakırköy','Fatih','Beyoğlu','Kartal','Pendik'],
  'İzmir':    ['Konak','Karşıyaka','Bornova','Buca','Çiğli','Gaziemir'],
  'Bursa':    ['Osmangazi','Nilüfer','Yıldırım','İnegöl'],
  'Antalya':  ['Muratpaşa','Kepez','Konyaaltı','Alanya']
};

function resetDoctors(msg){
  doctorSelect.innerHTML = '<option value="">-- Select Doctor --</option>';
  doctorSelect.disabled = true;
  doctorHelp.textContent = msg || 'Select hospital and department to see doctors.';
}

function populateDistricts(city){
  districtSelect.innerHTML = '<option value="">-- Select District --</option>';
  hospitalSelect.innerHTML = '<option value="">-- Select Hospital --</option>';
  resetDoctors();

  if (!city) return;

  (districtsByCity[city] || ['Merkez']).forEach(d=>{
    const opt = document.createElement('option');
    opt.value = d;
    opt.textContent = d;
    districtSelect.appendChild(opt);
  });
}

// Hospitals
const cityDistrictHospitals = {
  'Ankara': {
    'Çankaya': [
      'Ankara City Hospital',
      'Bilkent University Hospital',
      'Medicana Ankara Hospital',
      'Güven Hospital',
      'Çankaya State Hospital',
      'Medical Park Çankaya'
    ],
    'Keçiören': [
      'Keçiören Training and Research Hospital',
      'Etlik City Hospital',
      'Gülhane Military Medical Academy Hospital'
    ],
    'Yenimahalle': [
      'Yenimahalle State Hospital',
      'Yenimahalle Training and Research Hospital',
      'Medical Park Ankara',
      'Medisa Ankara Hospital',
      'Yıldırım Beyazıt University Hospital',
      'VIP Hospital Ankara',
      'Özel Bilgi Hospital'
    ],
    'Etimesgut': [
      'Etimesgut State Hospital',
      'Lokman Hekim Etimesgut Hospital'
    ]
  },
  'İstanbul': {
    'Şişli': [
      'Acıbadem Maslak Hospital',
      'Memorial Şişli Hospital',
      'Şişli Etfal Training and Research Hospital'
    ],
    'Beşiktaş': [
      'American Hospital',
      'Acıbadem Fulya Hospital',
      'Özel Tanfer Hospital'
    ],
    'Kadıköy': [
      'Acıbadem Kadıköy Hospital',
      'Medicana Kadıköy Hospital',
      'Kadıköy State Hospital'
    ],
    'Fatih': [
      'Istanbul University Hospital',
      'Private Fatih Hospital',
      'Çapa Faculty of Medicine Hospital'
    ],
    'Beyoğlu': [
      'Beyoğlu State Hospital',
      'Memorial Taksim Hospital'
    ],
    'Bakırköy': [
      'Bakırköy State Hospital',
      'Acıbadem Bakırköy Hospital'
    ]
  },
  'İzmir': {
    'Konak': [
      'Ege University Hospital',
      'Dokuz Eylül University Hospital'
    ],
    'Karşıyaka': [
      'Karşıyaka State Hospital'
    ],
    'Bornova': [
      'Bornova State Hospital'
    ]
  },
  'Antalya': {
    'Muratpaşa': [
      'Antalya Training and Research Hospital'
    ],
    'Konyaaltı': [
      'Akdeniz University Hospital'
    ]
  }
};

function populateHospitals(city, district){
  hospitalSelect.innerHTML = '<option value="">-- Select Hospital --</option>';
  resetDoctors();

  if (!city || !district) return;

  const list = (cityDistrictHospitals[city] && cityDistrictHospitals[city][district])
    ? cityDistrictHospitals[city][district]
    : [district + ' State Hospital', district + ' Training and Research Hospital'];

  list.forEach(h=>{
    const opt = document.createElement('option');
    opt.value = h;
    opt.textContent = h;
    hospitalSelect.appendChild(opt);
  });
}

function populateDoctorsStrict(){
  const hosp = hospitalSelect.value;
  const dept = departmentSelect.value;

  doctorSelect.innerHTML = '<option value="">-- Select Doctor --</option>';
  doctorSelect.disabled = true;

  if (!hosp || !dept) {
    resetDoctors();
    return;
  }

  const list = DOCTORS.filter(d =>
    norm(d.hospital_name) === norm(hosp) &&
    norm(d.department) === norm(dept)
  );

  if (list.length === 0) {
    resetDoctors('No doctors found for this hospital and department.');
    return;
  }

  list.forEach(d=>{
    const opt = document.createElement('option');
    opt.value = String(d.id);
    opt.textContent = d.name;
    doctorSelect.appendChild(opt);
  });

  doctorSelect.disabled = false;
  doctorHelp.textContent = 'Select a doctor.';
}

citySelect.addEventListener('change', ()=> populateDistricts(citySelect.value));
districtSelect.addEventListener('change', ()=>{
  populateHospitals(citySelect.value, districtSelect.value);
  populateDoctorsStrict();
});
hospitalSelect.addEventListener('change', populateDoctorsStrict);
departmentSelect.addEventListener('change', populateDoctorsStrict);

(function init(){
  if (INITIAL.city) {
    populateDistricts(INITIAL.city);
    districtSelect.value = INITIAL.district || '';
  }
  if (INITIAL.city && INITIAL.district) {
    populateHospitals(INITIAL.city, INITIAL.district);
    hospitalSelect.value = INITIAL.hospital || '';
  }
  populateDoctorsStrict();
  if (INITIAL.doctorId) doctorSelect.value = INITIAL.doctorId;
})();
</script>

</body>
</html>
