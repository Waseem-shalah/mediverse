<?php
// about.php
// This is the "About Us" page for MediVerse
// It uses Bootstrap and Font Awesome for styling and icons
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>About Us | MediVerse</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Font Awesome (for icons) -->
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    integrity="sha512-kuNm5bEFl+8Cx7jDWvM+G18l1zM5W4/jGqnYwYxEo0vwlJ4QYX+Ag3qpjevNzUdOcUeQZ1fY74dVXHU5yKQmMg=="
    crossorigin="anonymous"
    referrerpolicy="no-referrer"
  />

  <style>
    /* General page style */
    body {
      font-family: 'Segoe UI', sans-serif;
      background-color: #f6f9fc;
      margin: 0; padding: 0;
    }

    /* Main container for the About content */
    .about-container {
      max-width: 800px;
      margin: 60px auto;
      background: #ffffff;
      padding: 40px;
      border-radius: 12px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
      position: relative;
      overflow: hidden;
    }

    /* Decorative green circle in background */
    .about-container::before {
      content: '';
      position: absolute;
      top: -50px; right: -50px;
      width: 200px; height: 200px;
      background: rgba(46, 204, 113, 0.15);
      border-radius: 50%;
    }

    /* Section titles */
    .about-container h2 {
      text-align: center; color: #333; margin-bottom: 20px;
      position: relative;
    }
    .about-container h2::after {
      content: '';
      display: block;
      width: 60px; height: 4px;
      background-color: #2ecc71;
      margin: 12px auto 0;
      border-radius: 2px;
    }

    /* Paragraphs */
    .about-container p {
      color: #555; line-height: 1.7; margin-bottom: 1.2rem;
    }

    /* Subtitles (Mission, Key Features, etc.) */
    .about-container h3 {
      color: #2c3e50; margin-top: 2.5rem; margin-bottom: 1rem;
      position: relative;
      padding-left: 20px;
    }
    .about-container h3::before {
      content: '';
      position: absolute;
      left: 0; top: 8px;
      width: 12px; height: 12px;
      background-color: #3498db;
      border-radius: 50%;
    }

    /* Grid of features (responsive) */
    .feature-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 1.2rem; margin-top: 1rem;
    }

    /* Individual feature card */
    .feature-item {
      background: #fdfdfd;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.04);
      padding: 24px;
      text-align: center;
      transition: transform .3s ease, box-shadow .3s ease;
    }
    .feature-item:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    }
    .feature-item .icon {
      font-size: 2.5rem; color: #2ecc71;
      margin-bottom: 12px;
    }

    /* "Get Started" section buttons */
    .get-started {
      text-align: center; margin-top: 3rem;
    }
    .get-started .btn-primary {
      background-color: #2ecc71; border: none; padding: 14px 32px;
      font-size: 1.1rem; border-radius: 6px;
      transition: background-color .3s ease, transform .2s ease;
    }
    .get-started .btn-primary:hover {
      background-color: #28b463; transform: translateY(-2px);
    }
    .get-started .btn-outline-primary {
      padding: 12px 28px;
      font-size: 1rem;
      border-radius: 6px;
    }
  </style>
</head>
<body>

  <!-- Navbar (shared across site) -->
  <?php require 'navbar.php'; ?>

  <!-- About section main container -->
  <div class="about-container">
    <h2>About MediVerse</h2>
    <p>
      MediVerse is a modern, all-in-one healthcare platform dedicated to connecting patients with
      qualified doctors seamlessly. Our mission is to make high-quality medical care accessible
      to everyone, anywhere, at any time.
    </p>

    <!-- Mission section -->
    <h3>Our Mission</h3>
    <p>
      We believe that healthcare should be patient-centric, transparent, and convenient.
      MediVerse empowers patients to manage appointments, access medical records, and communicate
      securely with their healthcare providers—all from one intuitive dashboard.
    </p>

    <!-- Features section -->
    <h3>Key Features</h3>
    <div class="feature-grid">
      <div class="feature-item">
        <i class="fas fa-calendar-check icon"></i>
        <h5>Online Booking</h5>
        <p>Schedule visits with real-time availability from our network of vetted doctors.</p>
      </div>
      <div class="feature-item">
        <i class="fas fa-file-medical-alt icon"></i>
        <h5>Medical Reports</h5>
        <p>Store and review your medical history and lab results securely anytime.</p>
      </div>
      <div class="feature-item">
        <i class="fas fa-prescription-bottle-alt icon"></i>
        <h5>Prescription Management</h5>
        <p>Receive and track electronic prescriptions with ease.</p>
      </div>
      <div class="feature-item">
        <i class="fas fa-comments icon"></i>
        <h5>Secure Messaging</h5>
        <p>Chat with your care team in a HIPAA-compliant environment.</p>
      </div>
      <div class="feature-item">
        <i class="fas fa-star icon"></i>
        <h5>Ratings & Reviews</h5>
        <p>Read and leave feedback to help maintain quality across our network.</p>
      </div>
    </div>

    <!-- Why Choose Us section -->
    <h3>Why Choose Us?</h3>
    <p>
      By leveraging cutting-edge technology and a user-friendly interface, MediVerse reduces
      friction in the patient journey. Whether you need routine care or specialized treatment,
      our network of vetted professionals is here to support your health goals.
    </p>

    <!-- Call to action -->
    <h3>Get Started</h3>
    <p>
      Ready to experience healthcare reimagined?
    </p>
    <div class="get-started">
      <a href="register.php" class="btn btn-primary text-white">Sign Up Today</a>
      <a href="contact.php" class="btn btn-outline-primary ms-2">Contact Support</a>
    </div>
  </div>

  <!-- Bootstrap JS for interactive navbar -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
