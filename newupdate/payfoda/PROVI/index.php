<?php
// PROVI/index.php
// Access-controlled exam portal

require_once __DIR__ . '/../config/config.php';

$hash = isset($_GET['hash']) ? sanitize($_GET['hash']) : '';
$accessGranted = false;
$userData = null;

// Check if user has access via hash
if ($hash) {
    $userData = checkUserAccess($db, $hash);
    
    if ($userData) {
        $accessGranted = true;
        logAccess($db, $userData['id'], $hash, 'exam_access', 'granted');
    } else {
        logAccess($db, null, $hash, 'exam_access', 'denied');
        
        // Check for pending payment
        $sql = "SELECT u.email FROM users u WHERE u.unique_hash = :hash";
        $stmt = $db->prepare($sql);
        $stmt->execute([':hash' => $hash]);
        $user = $stmt->fetch();
        
        if ($user) {
            $sql = "SELECT id FROM payments WHERE email = :email AND status = 'pending' ORDER BY id DESC LIMIT 1";
            $stmt = $db->prepare($sql);
            $stmt->execute([':email' => $user['email']]);
            $pendingPayment = $stmt->fetch();
            
            if ($pendingPayment) {
                header('Location: waiting.php?email=' . urlencode($user['email']) . '&payment_id=' . $pendingPayment['id']);
                exit();
            }
        }
    }
}

// If accessed via email (from waiting page)
if (isset($_GET['email']) && !empty($_GET['email']) && !$accessGranted) {
    $email = sanitize($_GET['email']);
    
    // Check if user has access
    $sql = "SELECT u.has_access, u.access_expires, u.unique_hash, u.full_name 
            FROM users u 
            WHERE u.email = :email 
            AND u.has_access = 1 
            AND (u.access_expires IS NULL OR u.access_expires > NOW())";
    $stmt = $db->prepare($sql);
    $stmt->execute([':email' => $email]);
    $activeUser = $stmt->fetch();
    
    if ($activeUser) {
        header('Location: index.php?hash=' . $activeUser['unique_hash']);
        exit();
    }
    
    // Check for pending payment
    $sql = "SELECT id FROM payments WHERE email = :email AND status = 'pending' ORDER BY id DESC LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([':email' => $email]);
    $pendingPayment = $stmt->fetch();
    
    if ($pendingPayment) {
        header('Location: waiting.php?email=' . urlencode($email) . '&payment_id=' . $pendingPayment['id']);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="rw">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Ikizamini cyo Gutwara Ibinyabiziga</title>
  <!-- SEO Meta Tags -->
  <meta name="description" content="Gukora ikizamini cya provizwari cyo gutwara ibinyabiziga mu Rwanda. Menyereza ubumenyi bwawe muri amasomo y'ibinyabiziga na code USSD yo kwishyura.">
  <meta name="keywords" content="ikizamini cya provizwari, gutwara ibinyabiziga, amasomo y'ibinyabiziga, Rwanda driving test, USSD payment, quiz ibinyabiziga">
  <meta name="author" content="Driving Test Rwanda">
  <!-- Open Graph Tags -->
  <meta property="og:title" content="Ikizamini cyo Gutwara Ibinyabiziga - Rwanda">
  <meta property="og:description" content="Menyereza ubumenyi bwawe mu gutwara ibinyabiziga nikizamini cya provizwari. Ishyura uhereye kuri telefoni yawe na code USSD.">
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://example.com/ikizamini-cyo-gutwara-ibinyabiziga">
  <meta property="og:image" content="https://example.com/images/driving-test-og.jpg">
  <!-- Twitter Card Tags -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Ikizamini cyo Gutwara Ibinyabiziga - Rwanda">
  <meta name="twitter:description" content="Menyereza ubumenyi bwawe mu gutwara ibinyabiziga na quiz ya provizwari. Ishyura uhereye kuri telefoni yawe.">
  <meta name="twitter:image" content="https://example.com/images/driving-test-twitter.jpg">
  <!-- Canonical URL -->
  <link rel="canonical" href="https://example.com/ikizamini-cyo-gutwara-ibinyabiziga">
  <!-- Poppins Font -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <!-- Font Awesome for icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <style>
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      max-width: 900px;
      margin: 0 auto;
      padding: 20px;
      color: #2c3e50;
      background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
      min-height: 100vh;
      box-sizing: border-box;
      display: flex;
      flex-direction: column;
    }
    .hidden {
      display: none !important;
    }

    /* === FOOTER HIDE WHEN QUIZ IS ACTIVE === */
    footer.footer-hidden {
      display: none !important;
    }

    /* Card style wrapper */
    #home-screen, #quiz-screen, #result-screen, #review-screen {
      background: rgba(255, 255, 255, 0.96);
      padding: 30px;
      border-radius: 16px;
      box-shadow: 0 8px 25px rgba(0,0,0,0.2);
      margin-top: 40px;
      animation: fadeIn 0.6s ease;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(15px); }
      to { opacity: 1; transform: translateY(0); }
    }
    #image-container {
      margin: 15px 0;
      text-align: center;
    }
    .question-img {
      display: block;
      margin: 0 auto;
      max-width: 250px;
      border: 2px solid #ddd;
      padding: 8px;
      background-color: #fff;
      border-radius: 10px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      transition: all 0.3s ease;
    }
    .question-img:hover {
      transform: scale(1.05);
      box-shadow: 0 6px 18px rgba(0,0,0,0.25);
    }
    h1, h2 {
      text-align: center;
      margin-bottom: 20px;
      color: #1e3c72;
    }
    h1 {
      font-size: 2.4em;
      margin-bottom: 10px;
    }
    /* OPTION STYLE */
    .option-label {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      padding: 15px;
      margin-bottom: 12px;
      border: 2px solid #e0e0e0;
      border-radius: 12px;
      cursor: pointer;
      background: #fff;
      transition: all 0.3s ease;
      box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    .option-label:hover {
      background-color: #f1f7ff;
      border-color: #007bff;
      transform: translateY(-2px);
      box-shadow: 0 6px 15px rgba(0,123,255,0.25);
    }
    .option-label.selected {
      background-color: #e3f2fd;
      border-color: #2196f3;
      box-shadow: 0 6px 15px rgba(33,150,243,0.3);
      transform: translateY(-1px);
    }
    .option-label input {
      display: none;
    }
    .option-circle {
      width: 32px;
      height: 32px;
      border: 2px solid #007bff;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      color: #007bff;
      font-size: 14px;
      transition: all 0.3s ease;
      flex-shrink: 0;
      margin-top: 2px;
    }
    .option-label input:checked + .option-circle {
      background-color: #007bff;
      color: #fff;
      border-color: #0056b3;
    }
    .option-text {
      flex: 1;
      line-height: 1.4;
      color: #2c3e50;
      font-size: 15px;
    }
    #timer {
      font-size: 24px;
      font-weight: bold;
      margin: 15px 0 25px 0;
      color: #e74c3c;
      text-align: center;
      background: rgba(231, 76, 60, 0.12);
      padding: 10px 20px;
      border-radius: 10px;
      display: inline-block;
      box-shadow: inset 0 2px 6px rgba(0,0,0,0.08);
    }
    button {
      margin: 8px 4px;
      padding: 12px 28px;
      font-size: 16px;
      cursor: pointer;
      border: none;
      border-radius: 10px;
      background: linear-gradient(135deg, #007bff, #0056b3);
      color: white;
      transition: all 0.3s ease;
      font-weight: 600;
      box-shadow: 0 4px 10px rgba(0,123,255,0.3);
      touch-action: manipulation;
    }
    button:hover:not(:disabled) {
      background: linear-gradient(135deg, #0056b3, #00408d);
      transform: translateY(-2px) scale(1.02);
      box-shadow: 0 6px 15px rgba(0,123,255,0.4);
    }
    button:disabled {
      background: #6c757d;
      cursor: not-allowed;
      opacity: 0.6;
      box-shadow: none;
    }
    #nav-buttons {
      text-align: center;
      margin-top: 25px;
      padding-top: 20px;
      border-top: 1px solid #e0e0e0;
      display: flex;
      justify-content: center;
      gap: 10px;
      flex-wrap: wrap;
    }
    #review-container .question-block {
      background: #fff;
      padding: 25px;
      margin-bottom: 25px;
      border-radius: 12px;
      border: 1px solid #e0e0e0;
      box-shadow: 0 6px 18px rgba(0,0,0,0.12);
    }
    /* Payment section styles */
    section {
      margin: 20px 0;
      font-family: "Poppins", sans-serif;
      background: linear-gradient(135deg, #e3f2fd, #bbdefb);
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    }
    #mainPage {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 15px;
      width: 100%;
      max-width: 400px;
    }
    #amountInput {
      padding: 12px;
      font-size: 16px;
      border-radius: 8px;
      border: 1px solid #ccc;
      width: 100%;
      max-width: 250px;
      box-sizing: border-box;
      transition: border-color 0.3s ease, box-shadow 0.3s ease;
      text-align: center;
      font-weight: bold;
      color: #1e3c72;
    }
    #amountInput:focus {
      outline: none;
      border-color: #007bff;
      box-shadow: 0 0 5px rgba(0,123,255,0.3);
    }
    #messageBox {
      color: #dc3545;
      font-size: 14px;
      text-align: center;
      min-height: 20px;
      margin-top: 10px;
      width: 100%;
      max-width: 300px;
    }
    #payBtn {
      background: linear-gradient(135deg, #4CAF50, #2e7d32);
      color: white;
      padding: 12px 30px;
      font-size: 18px;
      font-weight: bold;
      border: none;
      border-radius: 12px;
      cursor: pointer;
      box-shadow: 0px 6px 15px rgba(0, 0, 0, 0.25);
      transition: all 0.3s ease;
      width: 100%;
      max-width: 250px;
      touch-action: manipulation;
    }
    #payBtn:hover {
      background: linear-gradient(135deg, #43a047, #1b5e20);
      transform: scale(1.05);
    }
    /* Animation for shaking */
    @keyframes shake {
      0% { transform: translateX(0); }
      25% { transform: translateX(-6px); }
      50% { transform: translateX(6px); }
      75% { transform: translateX(-6px); }
      100% { transform: translateX(0); }
    }
    .shake {
      animation: shake 0.4s;
    }
    /* Code page */
    .code-page {
      display: none;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      height: 60vh;
      background: linear-gradient(135deg, #f1f8e9, #c8e6c9);
      text-align: center;
      padding: 20px;
      border-radius: 12px;
    }
    .code-page h1 {
      color: #2e7d32;
      font-size: 28px;
      margin-bottom: 20px;
    }
    .code-box {
      background: white;
      padding: 20px 30px;
      border-radius: 10px;
      box-shadow: 0px 5px 12px rgba(0, 0, 0, 0.2);
      font-size: 22px;
      font-weight: bold;
      color: #1b5e20;
      word-break: break-all;
      max-width: 90%;
    }
    .back-btn {
      margin-top: 25px;
      padding: 12px 25px;
      background: #2e7d32;
      color: white;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-size: 18px;
      transition: 0.3s;
      width: 100%;
      max-width: 200px;
      touch-action: manipulation;
    }
    .back-btn:hover {
      background: #1b5e20;
    }
    /* Hidden dial link */
    #dialLink {
      display: none;
    }
    /* Result screen styling */
    #result-screen {
      text-align: center;
      padding: 40px 20px;
    }
    #score {
      font-size: 4.5em;
      color: #28a745;
      margin: 20px 0;
      font-weight: bold;
      text-shadow: 2px 2px 6px rgba(0,0,0,0.2);
    }
    #pass-message {
      font-size: 1.6em;
      margin: 25px 0;
      font-weight: bold;
      padding: 15px 25px;
      border-radius: 10px;
      display: inline-block;
    }
    #pass-message:where([style*="color: green"]) {
      background: rgba(40, 167, 69, 0.12);
      color: #28a745;
      border: 2px solid rgba(40, 167, 69, 0.3);
    }
    #pass-message:where([style*="color: red"]) {
      background: rgba(220, 53, 69, 0.12);
      color: #dc3545;
      border: 2px solid rgba(220, 53, 69, 0.3);
    }
    /* Footer styles */
    footer {
      margin-top: auto;
      padding: 10px 0;
      width: 100%;
    }
    .counter-section {
      display: flex;
      justify-content: space-around;
      align-items: center;
      flex-wrap: wrap;
      gap: 15px;
      padding: 0px;
      border-radius: 2px;
      margin: 0 auto;
      max-width: 100%;
    }
    .counter-box {
      text-align: center;
      flex: 1;
      min-width: 10px;
      padding: 2px;
      transition: transform 0.3s ease;
      opacity: 0;
      animation: fadeInCounter 1s ease forwards;
      animation-delay: calc(var(--counter-index) * 0.2s);
    }
    .counter-box:nth-child(1) { --counter-index: 1; }
    .counter-box:nth-child(2) { --counter-index: 2; }
    .counter-box:nth-child(3) { --counter-index: 3; }
    @keyframes fadeInCounter {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .counter-box:hover {
      transform: translateY(-5px);
    }
    .counter-box i {
      font-size: 28px;
      margin-bottom: 12px;
    }
    .counter-box i.fa-users, .counter-box i.fa-graduation-cap {
      color: #05ceb6;
    }
    .counter-box i.fa-heart {
      color: #d40a1f;
    }
    .counter-number {
      font-size: 2.2em;
      font-weight: bold;
      color: #eceef0;
      margin: 10px 0;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 5px;
    }
    .counter-label {
      font-size: 1em;
      color: #f6f8fa;
      font-family: 'Poppins', sans-serif;
    }
    #appreciate-btn {
      background: linear-gradient(135deg, #cc0317, #c20518);
      padding: 10px 20px;
      font-size: 14px;
      margin: 10px auto;
      display: block;
      max-width: 200px;
    }
    #appreciate-btn:hover:not(:disabled) {
      background: linear-gradient(135deg, #a71d2a, #7a1a1f);
      transform: translateY(-2px) scale(1.02);
    }
    /* Access denied message */
    .access-denied {
      background: rgba(255, 255, 255, 0.96);
      border-radius: 16px;
      padding: 60px 30px;
      text-align: center;
      margin-top: 40px;
    }
    .access-denied .icon {
      font-size: 64px;
      margin-bottom: 20px;
    }
    .access-denied h2 {
      color: #dc3545;
    }
    .access-denied .btn {
      display: inline-block;
      padding: 12px 30px;
      background: linear-gradient(135deg, #28a745, #1e7e34);
      color: white;
      text-decoration: none;
      border-radius: 8px;
      margin-top: 20px;
      font-weight: bold;
    }
    .access-denied .btn:hover {
      background: linear-gradient(135deg, #1e7e34, #155724);
      transform: translateY(-2px);
    }
    @media (max-width: 768px) {
      body {
        padding: 15px;
      }
      h1 {
        font-size: 2em;
      }
      h2 {
        font-size: 1.5em;
      }
      #home-screen, #quiz-screen, #result-screen, #review-screen {
        padding: 20px;
        margin-top: 20px;
      }
      section {
        padding: 15px;
        height: auto;
        min-height: 40vh;
      }
      .counter-section {
        flex-direction: column;
        padding: 15px;
        gap: 12px;
      }
      .counter-box {
        min-width: 100%;
        padding: 12px;
      }
    }
    @media (max-width: 500px) {
      body {
        padding: 10px;
      }
      h1 {
        font-size: 1.8em;
      }
      button {
        width: 100%;
        margin: 8px 0;
      }
      #nav-buttons {
        flex-direction: column-reverse;
        gap: 8px;
      }
      .access-denied {
        padding: 40px 20px;
      }
    }
  </style>
</head>
<body>
  <?php if ($accessGranted && $userData): ?>
  <!-- ACCESS GRANTED - SHOW EXAM -->
  <div id="home-screen">
    <h1>Ikizamini cya Provizwari</h1>
    <p>Murakaza neza <strong><?php echo htmlspecialchars($userData['full_name']); ?></strong>! Mugiye gukora ikizamini cya <strong>amasomo y'ibinyabiziga</strong>.</p>
    <?php if ($userData['access_expires']): ?>
      <div style="background: #e8f5e9; padding: 10px; border-radius: 8px; margin: 15px 0; text-align: center;">
        ⏰ Access ikurikira kugeza: <strong><?php echo date('d M Y', strtotime($userData['access_expires'])); ?></strong>
      </div>
    <?php endif; ?>
    <ul>
      <li>Ibibazo <strong>20</strong> bitoranywa mu<em>bubiko </em> bugari.</li>
      <li>Igihe: <strong>iminota 20</strong>. Nimara kurangira, ikizamini <strong>cyoherezwaho</strong> ako kanya.</li>
      <li>Hitamo igisubizo <strong>kimwe</strong> (A–D), ukande <strong>"Komeza"</strong> kugira ngo ujye ku kibazo gikurikira.</li>
      <li>Nyuma yo kohereza: mubona <strong>Amanota</strong> ukanabona <strong> Ibisubizo</strong>wakabaye wahisemo.</li>
    </ul>
    <div style="text-align: center;">
      <button id="start-btn">Tangira Ikizamini</button>
    </div>
  </div>
  
  <!-- QUIZ SCREEN -->
  <div id="quiz-screen" class="hidden">
    <div id="timer" role="timer">20:00</div>
    <h2 id="question-text"></h2>
    <div id="image-container"></div>
    <form id="options-form" role="form">
      <label class="option-label" id="label-a">
        <input type="radio" name="option" value="a" aria-labelledby="option-a" />
        <span class="option-circle">A</span>
        <span id="option-a" class="option-text"></span>
      </label>
      <label class="option-label" id="label-b">
        <input type="radio" name="option" value="b" aria-labelledby="option-b" />
        <span class="option-circle">B</span>
        <span id="option-b" class="option-text"></span>
      </label>
      <label class="option-label" id="label-c">
        <input type="radio" name="option" value="c" aria-labelledby="option-c" />
        <span class="option-circle">C</span>
        <span id="option-c" class="option-text"></span>
      </label>
      <label class="option-label" id="label-d">
        <input type="radio" name="option" value="d" aria-labelledby="option-d" />
        <span class="option-circle">D</span>
        <span id="option-d" class="option-text"></span>
      </label>
    </form>
    <div id="nav-buttons">
      <button id="prev-btn" disabled>Gusubira Inyuma</button>
      <button id="next-btn" disabled>Gukomeza</button>
      <button id="submit-btn" class="hidden">Ohereza Ibisubizo</button>
    </div>
  </div>
  
  <!-- RESULT SCREEN -->
  <div id="result-screen" class="hidden">
    <h2>Ugize amanota:</h2>
    <h1 id="score"></h1>
    <p id="pass-message"></p>
    <div style="text-align:center; margin-top: 30px; gap: 15px; display: flex; flex-direction: column; align-items: center;">
      <button id="review-btn">Reba Ibisubizo wakabaye uhitamo</button>
      <button id="restart-btn">Garuka Ahabanza</button>
    </div>
  </div>
  
  <!-- REVIEW SCREEN -->
  <div id="review-screen" class="hidden">
    <h2>Gusubiramo Ibisubizo</h2>
    <div id="review-container"></div>
    <div style="text-align:center; margin-top: 25px;">
      <button id="finish-btn">Garuka Ahabanza</button>
    </div>
  </div>
  
  <!-- FOOTER -->
  <footer>
    <div class="counter-section">
      <div class="counter-box">
        <i class="fas fa-users"></i>
        <div class="counter-number" id="visitor-counter">0</div>
        <div class="counter-label">Website Visitors</div>
      </div>
      <div class="counter-box">
        <i class="fas fa-heart"></i>
        <div class="counter-number" id="appreciate-counter">0</div>
        <div class="counter-label">People Who Appreciate Our Work</div>
      </div>
      <div class="counter-box">
        <i class="fas fa-graduation-cap"></i>
        <div class="counter-number" id="pass-counter">0</div>
        <div class="counter-label">People Who Passed the Exam</div>
      </div>
    </div>
    <button id="appreciate-btn">Murakoze Kushyigikira</button>
  </footer>
  
  <script>
    // Pass user data to JavaScript
    window.userData = {
      id: <?php echo $userData['id']; ?>,
      email: "<?php echo $userData['email']; ?>",
      hash: "<?php echo $hash; ?>"
    };
  </script>
  <script src="app.js"></script>
  
  <?php else: ?>
  <!-- ACCESS DENIED - SHOW PAYMENT OPTION -->
  <div class="access-denied">
    <div class="icon">🔒</div>
    <h2>Ntabwo ufite uburenganzira</h2>
    <p>Uru rubuga rurakingiye. Kugira ngo ubone ikizamini, ugomba kwishyura mbere.</p>
    <p>Kanda kuri button hepfo kugira ngo utangire kwishyura.</p>
    <a href="../pay.php" class="btn">Kanda WISHYURE</a>
  </div>
  <?php endif; ?>
</body>
</html>