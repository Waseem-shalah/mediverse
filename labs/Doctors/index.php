<!-- index.php -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>MediVerse | Home</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    /* General page style */
    body {
      font-family: 'Segoe UI', sans-serif;
      background-color: #f9f9f9;
    }

    /* Hero (welcome banner) */
    .hero {
      background: linear-gradient(90deg, #60a5fa20, #22d3ee20), var(--bg);
      color: white;
      padding: 100px 0;
    }
    .hero h1 {
      font-size: 3rem;
    }
    .hero p {
      font-size: 1.25rem;
    }

    /* Generic section spacing */
    .section {
      padding: 60px 0;
    }
  </style>
</head>
<body>

  <!-- Top navigation bar -->
  <?php include 'navbar.php'; ?>

  <!-- Hero section -->
  <div class="hero text-center">
    <div class="container">
      <h1>Welcome to MediVerse</h1>
      <p>Your health, your schedule. Book appointments with trusted doctors online.</p>
      <a href="login.php" class="btn btn-light btn-lg mt-3">Get Started</a>
    </div>
  </div>

  <!-- Features section -->
  <div class="section text-center">
    <div class="container">
      <h2>Features</h2>
      <p class="lead">From booking to prescriptions — everything in one place.</p>
      <div class="row mt-4">
        <div class="col-md-4">
          <h5>Book Appointments</h5>
          <p>Schedule visits with available doctors directly from the system.</p>
        </div>
        <div class="col-md-4">
          <h5>Chat with Doctors</h5>
          <p>Secure messaging system for patients and doctors.</p>
        </div>
        <div class="col-md-4">
          <h5>Prescriptions & Reports</h5>
          <p>Access prescriptions, visit summaries, and medical reports easily.</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <footer class="text-center py-4 bg-light mt-5">
    <p class="mb-0">© <?= date('Y') ?> MediVerse. All rights reserved.</p>
  </footer>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
