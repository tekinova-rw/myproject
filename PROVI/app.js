// PROVI/app.js
const homeScreen = document.getElementById('home-screen');
const quizScreen = document.getElementById('quiz-screen');
const resultScreen = document.getElementById('result-screen');
const reviewScreen = document.getElementById('review-screen');

const startBtn = document.getElementById('start-btn');
const prevBtn = document.getElementById('prev-btn');
const nextBtn = document.getElementById('next-btn');
const submitBtn = document.getElementById('submit-btn');
const restartBtn = document.getElementById('restart-btn');
const reviewBtn = document.getElementById('review-btn');
const homeBtn = document.getElementById('home-btn');

const timerEl = document.getElementById('timer');
const questionText = document.getElementById('question-text');
const imageContainer = document.getElementById('image-container');
const optionsForm = document.getElementById('options-form');
const scoreEl = document.getElementById('score');
const passMessage = document.getElementById('pass-message');
const reviewContainer = document.getElementById('review-container');
const progressBar = document.getElementById('progress-bar');
const navButtons = document.getElementById('nav-buttons');

let questionsPool = [];
let questions = [];
let currentIndex = 0;
let selectedAnswers = [];
let timerId = null;
let timeLeft = 20 * 60; // 20 minutes in seconds
let warnedLastMinute = false;
let quizEndTime = null;
let lastActivity = Date.now();
let inactivityWarned = false;

let fullscreenEnabled = false;
let hasEverEnteredFullscreen = false; 
let fullscreenExitStrikes = 0;       

let currentIP = null;
const authorizedPassword = 'exam2025'; 
const ENCRYPTION_KEY = 'quiz_secure_key_2025';
const MIN_QUESTIONS_REQUIRED = 20;

const isMobile = /Android|iPhone|iPad|iPod|Mobile|Windows Phone/i.test(
  navigator.userAgent || ''
);

// ==========================================
// === CUSTOM AUTO-DISMISS NOTIFICATION ===
// ==========================================
function showToast(message) {
  let toast = document.getElementById('custom-toast');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'custom-toast';
    toast.style.cssText = `
      position: fixed;
      top: 70px;
      left: 50%;
      transform: translateX(-50%);
      background: rgba(231, 76, 60, 0.95);
      color: white;
      padding: 15px 25px;
      border-radius: 8px;
      font-size: 16px;
      font-weight: bold;
      text-align: center;
      z-index: 10000;
      box-shadow: 0 4px 15px rgba(0,0,0,0.3);
      transition: opacity 0.3s ease, transform 0.3s ease;
      max-width: 90%;
      cursor: pointer;
    `;
    document.body.appendChild(toast);

    document.body.addEventListener('click', () => {
      if (toast.style.display === 'block') {
        toast.style.opacity = '0';
        toast.style.transform = 'translate(-50%, -20px)';
        setTimeout(() => { toast.style.display = 'none'; }, 300);
      }
    });
  }
  
  toast.textContent = message;
  toast.style.opacity = '1';
  toast.style.display = 'block';
  toast.style.transform = 'translate(-50%, 0)';

  if (window.toastTimeout) clearTimeout(window.toastTimeout);

  window.toastTimeout = setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translate(-50%, -20px)';
    setTimeout(() => { toast.style.display = 'none'; }, 300);
  }, 3000);
}

// ==========================================
// === AUTO-REFRESH OVERLAY (2-Minute Wait) ===
// ==========================================
function showAutoRefreshOverlay() {
  let overlay = document.getElementById('auto-refresh-overlay');
  if (!overlay) {
    overlay = document.createElement('div');
    overlay.id = 'auto-refresh-overlay';
    overlay.style.cssText = `
      position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
      background: rgba(15, 32, 39, 0.95); color: white;
      display: flex; flex-direction: column; justify-content: center; align-items: center;
      z-index: 999999; text-align: center; padding: 20px; font-family: 'Segoe UI', sans-serif;
    `;
    overlay.innerHTML = `
      <h1 style="color: #f1c40f; margin-bottom: 20px;">Tegereza Gato...</h1>
      <p style="font-size: 18px; margin-bottom: 30px;">Sisitemu iri gukora ivugurura. Nyamuneka tegereza akanya gato.</p>
      <div id="refresh-countdown" style="font-size: 48px; font-weight: bold; color: #f1c40f; background: rgba(0,0,0,0.5); padding: 10px 30px; border-radius: 10px;">02:00</div>
      <p style="font-size: 14px; color: #bdc3c7; margin-top: 30px;">Ntukeneye gukora icyo ari cyo cyose. Iyi page iziyongera ubwayo.</p>
    `;
    document.body.appendChild(overlay);
  }

  // Start the 2-minute countdown
  let countdownTime = 2 * 60; // 2 minutes in seconds
  const countdownEl = document.getElementById('refresh-countdown');

  const interval = setInterval(() => {
    countdownTime--;
    
    if (countdownTime <= 0) {
      clearInterval(interval);
      // Clear the used questions cache for this IP so they can start fresh
      if (currentIP) {
        safeLocalStorage(`usedQuestionsByIP`, 'set', null); // Clears all used questions
      }
      window.location.reload(); // Automatically refresh the page
      return;
    }

    const mins = Math.floor(countdownTime / 60);
    const secs = countdownTime % 60;
    countdownEl.textContent = `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
  }, 1000);
}


// === Track completed exams per IP ===
function getCompletedExamCount(ip) {
  const key = `completedExams_${ip}`;
  return safeLocalStorage(key, 'get') || 0;
}

function incrementCompletedExamCount(ip) {
  const key = `completedExams_${ip}`;
  const count = (safeLocalStorage(key, 'get') || 0) + 1;
  safeLocalStorage(key, 'set', count);
  return count;
}

async function getIP() {
  try {
    const res = await fetch('https://api.ipify.org?format=json');
    if (!res.ok) throw new Error('Failed to fetch IP');
    const data = await res.json();
    safeLocalStorage('fallbackIP', 'set', data.ip);
    return data.ip;
  } catch (err) {
    console.warn('IP detection failed, using fallback:', err);
    const existing = safeLocalStorage('fallbackIP', 'get');
    if (existing) return existing;
    const seed = (navigator.userAgent || '') + '|' + Intl.DateTimeFormat().resolvedOptions().timeZone;
    const fallback = 'fallback_' + simpleHash(seed);
    safeLocalStorage('fallbackIP', 'set', fallback);
    return fallback;
  }
}

function simpleHash(str) {
  let h = 2166136261 >>> 0;
  for (let i = 0; i < str.length; i++) {
    h ^= str.charCodeAt(i);
    h = Math.imul(h, 16777619);
  }
  return (h >>> 0).toString(36);
}

function getUsedQuestionsForIP(ip) {
  const data = safeLocalStorage('usedQuestionsByIP', 'get') || {};
  const ipData = Array.isArray(data[ip]) ? data[ip] : [];
  const now = Date.now();
  const fresh = ipData.filter(q => q && q.id && (now - q.time) < 3600000);
  data[ip] = fresh;
  safeLocalStorage('usedQuestionsByIP', 'set', data);
  return new Set(fresh.map(q => q.id));
}

function saveUsedQuestionForIP(ip, questionId) {
  if (!questionId) return;
  const data = safeLocalStorage('usedQuestionsByIP', 'get') || {};
  if (!data[ip]) data[ip] = [];
  const now = Date.now();
  const ipData = data[ip];
  if (!ipData.some(q => q.id === questionId && (now - q.time) < 3600000)) {
    ipData.push({ id: questionId, time: now });
    data[ip] = ipData;
    safeLocalStorage('usedQuestionsByIP', 'set', data);
  }
}

function safeLocalStorage(key, operation = 'get', value = null) {
  try {
    const obfuscatedKey = btoa(key + ENCRYPTION_KEY);
    if (operation === 'set') {
      if (value === null || typeof value === 'undefined') {
        localStorage.removeItem(obfuscatedKey);
        return true;
      } else {
        localStorage.setItem(
          obfuscatedKey,
          btoa(unescape(encodeURIComponent(JSON.stringify(value))))
        );
        return true;
      }
    } else {
      const data = localStorage.getItem(obfuscatedKey);
      return data ? JSON.parse(decodeURIComponent(escape(atob(data)))) : null;
    }
  } catch (err) {
    console.error(`LocalStorage error for ${key}:`, err);
    return operation === 'set' ? false : null;
  }
}

function safeElementAccess(el, fallback = null) {
  return el || { textContent: '', innerHTML: '', classList: { add: () => {}, remove: () => {}, toggle: () => {} }, style: {}, disabled: false };
}

setInterval(() => {
  try {
    const now = Date.now();
    const usedData = safeLocalStorage('usedQuestionsByIP', 'get') || {};
    Object.keys(usedData).forEach(ip => {
      usedData[ip] = (usedData[ip] || []).filter(q => q && q.id && (now - q.time) < 3600000);
      if (usedData[ip].length === 0) delete usedData[ip];
    });
    safeLocalStorage('usedQuestionsByIP', 'set', usedData);
  } catch (err) {}
}, 600000);

document.addEventListener('contextmenu', e => e.preventDefault());
document.addEventListener('keydown', e => {
  if (e.key === 'F12' || (e.ctrlKey && e.shiftKey && e.key === 'I')) e.preventDefault();
});
document.addEventListener('selectstart', e => e.preventDefault());
document.addEventListener('keydown', e => {
  const forbiddenKeys = [
    (e.ctrlKey || e.metaKey) && (e.key === 'c' || e.key === 'x' || e.key === 'v' || e.key === 'a'),
    (e.ctrlKey || e.metaKey) && e.key === 'p',
    e.key === 'PrintScreen'
  ];
  if (forbiddenKeys.some(condition => condition)) {
    e.preventDefault();
    return false;
  }
});
document.addEventListener('dragstart', e => e.preventDefault());
document.addEventListener('drop', e => e.preventDefault());

window.addEventListener('pageshow', e => {
  if (e.persisted && quizScreen && !quizScreen.classList.contains('hidden')) {
    if (quizEndTime) {
      timeLeft = Math.max(Math.floor((quizEndTime - Date.now()) / 1000), 0);
      updateTimerDisplay();
      if (timeLeft <= 0) endQuiz();
    }
    return;
  }
  if (e.persisted) window.location.reload();
});

let devToolsDetected = false;
function detectDevTools() {
  if (!quizScreen || quizScreen.classList.contains('hidden')) return;
  if (isMobile) return;
  const threshold = 100;
  if (window.outerHeight - window.innerHeight > threshold || window.outerWidth - window.innerWidth > threshold) {
    if (!devToolsDetected) {
      devToolsDetected = true;
      showToast('Ibikoresho bya developer byabonye. Ikizamini kirongera gutangira!');
      resetQuizToHome();
    }
  }
}
let devPollId = null;
function startDevPoll() { if (!devPollId) devPollId = setInterval(detectDevTools, 500); }
function stopDevPoll() { if (devPollId) { clearInterval(devPollId); devPollId = null; } }

function requestFullscreen() {
  const el = document.documentElement;
  try {
    if (el.requestFullscreen) el.requestFullscreen().catch(()=>{});
    else if (el.mozRequestFullScreen) el.mozRequestFullScreen();
    else if (el.webkitRequestFullscreen) el.webkitRequestFullscreen();
    else if (el.msRequestFullscreen) el.msRequestFullscreen();
  } catch (err) {}
}

function exitFullscreen() {
  try {
    if (document.exitFullscreen) document.exitFullscreen().catch(()=>{});
    else if (document.mozCancelFullScreen) document.mozCancelFullScreen();
    else if (document.webkitExitFullscreen) document.webkitExitFullscreen();
    else if (document.msExitFullscreen) document.msExitFullscreen();
  } catch (err) {}
}

function isFullscreenActive() {
  return !!(document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement || document.msFullscreenElement);
}

// === Fullscreen Loss Handler (Warn on 1st, Reset on 2nd) ===
let fsResetTimer = null;
function handleFullscreenLoss() {
  if (!quizScreen || quizScreen.classList.contains('hidden')) return;
  if (!fullscreenEnabled) return;
  if (isFullscreenActive()) return;

  if (!hasEverEnteredFullscreen) return;
  if (document.hidden) return;
  if (isMobile) return;

  clearTimeout(fsResetTimer);
  fsResetTimer = setTimeout(() => {
    if (!isFullscreenActive() && !document.hidden && fullscreenEnabled && hasEverEnteredFullscreen) {
      fullscreenExitStrikes++;
      
      if (fullscreenExitStrikes === 1) {
        showToast('Urahiye gusohoka kuri fullscreen. Nyamuneka subira kuri fullscreen ukomeze ikizamini! (Uramburwa rimwe gusa)');
        requestFullscreen();
      } else {
        showToast('Ntushobora gusohoka kuri fullscreen. Ikizamini kirongera!');
        resetQuizToHome();
      }
    }
  }, 800);
}
document.addEventListener('fullscreenchange', handleFullscreenLoss);
document.addEventListener('webkitfullscreenchange', handleFullscreenLoss);
document.addEventListener('mozfullscreenchange', handleFullscreenLoss);
document.addEventListener('MSFullscreenChange', handleFullscreenLoss);

function ensureFullscreenWithPrompt() {
  if (!fullscreenEnabled || isFullscreenActive()) return;
  requestFullscreen();

  setTimeout(() => {
    if (isFullscreenActive() || !fullscreenEnabled || document.hidden) return;
    if (document.getElementById('fs-banner')) return;

    const banner = document.createElement('div');
    banner.id = 'fs-banner';
    banner.style.cssText = 'position:fixed;top:0;left:0;right:0;background:#c00;color:#fff;padding:16px;text-align:center;z-index:99999;font-size:16px;cursor:pointer;';
    banner.textContent = 'Kanda hano kugirango usubire kuri fullscreen ukomeze ikizamini';
    banner.onclick = () => { requestFullscreen(); banner.remove(); };
    document.body.appendChild(banner);

    const check = setInterval(() => {
      if (isFullscreenActive()) { clearInterval(check); banner.remove(); }
    }, 500);
  }, 500);
}

// === Visibility (Minimize) Handler - NOW ALLOWS CONTINUING ===
let hiddenTabWarned = false;
let minimizeDetected = false;
let wasHidden = false;

document.addEventListener('visibilitychange', () => {
  if (!quizScreen || quizScreen.classList.contains('hidden')) return;

  if (document.hidden) {
    // User left the exam (phone call, notification, app switch, minimize)
    wasHidden = true;
    minimizeDetected = true;
    lastActivity = Date.now();
    console.log('User left exam — timer continues silently');
    return;
  }

  // User returned
  if (wasHidden) {
    wasHidden = false;

    if (quizEndTime) {
      timeLeft = Math.max(Math.floor((quizEndTime - Date.now()) / 1000), 0);
      updateTimerDisplay();
    }

    // If time expired while they were away -> submit (don't reset)
    if (timeLeft <= 0) {
      console.log('Timer expired while away — submitting');
      endQuiz();
      return;
    }

    // Force back to fullscreen (will show tap banner if browser blocks it)
    ensureFullscreenWithPrompt();

    // Gentle notice for longer absences, but do NOT reset
    const absenceTime = Date.now() - lastActivity;
    if (absenceTime > 30000 && !hiddenTabWarned) {
      hiddenTabWarned = true;
      showToast('Wagarutse. Igihe cyawe cyakomeje kugenda. Komeza ikizamini.');
    }

    lastActivity = Date.now();
  }
});

document.addEventListener('mousemove', () => { lastActivity = Date.now(); });
document.addEventListener('keydown', () => { lastActivity = Date.now(); });

function inactivityCheck() {
  if (!quizScreen || quizScreen.classList.contains('hidden') || document.hidden || wasHidden) return;
  const inactiveTime = Date.now() - lastActivity;
  if (inactiveTime > 120000 && !inactivityWarned) {
    inactivityWarned = true;
    showToast('Ntabwo uri mu ikizamini? Ikizamini kizahagarara mu munota 1!');
    setTimeout(() => {
      if (!document.hidden && !wasHidden && Date.now() - lastActivity > 180000) endQuiz();
    }, 60000);
  }
}
setInterval(inactivityCheck, 5000);

let alertAudio = null;
function playAlertSound() { if (alertAudio) alertAudio.play().catch(() => {}); }

async function loadQuestions() {
  try {
    const res = await fetch('questions.json');
    if (!res.ok) throw new Error(`Failed to load questions.json: ${res.statusText}`);
    const externalQuestions = await res.json();
    
    questionsPool = externalQuestions
      .filter(q => q && q.id && q.question && ['a', 'b', 'c', 'd'].every(key => q[key]) && ['a', 'b', 'c', 'd'].includes((q.answer || '').toLowerCase()))
      .map(q => ({
        id: String(q.id), question: q.question, image: q.image ? `images/${q.image}` : null,
        a: q.a, b: q.b, c: q.c, d: q.d, answer: (q.answer || '').toLowerCase()
      }));

    if (questionsPool.length < MIN_QUESTIONS_REQUIRED) throw new Error('Insufficient questions in questions.json');
    
    safeElementAccess(startBtn).disabled = false;
  } catch (err) {
    showToast('Error: Questions could not be loaded. Check console.');
    safeElementAccess(startBtn).disabled = true;
  }
}
loadQuestions();

async function getExamSet(allQuestions, count = 20, ip, iteration = 0) {
  const used = getUsedQuestionsForIP(ip);
  let available = allQuestions.filter(q => !used.has(q.id));

  if (available.length < count) {
    showAutoRefreshOverlay();
    return [];
  }

  const completedExams = getCompletedExamCount(ip);
  const examInCycle = (completedExams % 6);
  const effectiveExamNumber = examInCycle === 0 ? 6 : examInCycle;
  const shouldHaveImages = [1, 2, 5, 6].includes(effectiveExamNumber);

  let selected = [];
  if (shouldHaveImages) {
    const imageQuestions = available.filter(q => q.image);
    const noImageQuestions = available.filter(q => !q.image);
    if (imageQuestions.length < 3 || noImageQuestions.length < (count - 3)) {
      showAutoRefreshOverlay();
      return [];
    }
    selected = selected.concat(reservoirSample(imageQuestions, 3), reservoirSample(noImageQuestions, count - 3));
  } else {
    const noImageQuestions = available.filter(q => !q.image);
    if (noImageQuestions.length < count) {
      showAutoRefreshOverlay();
      return [];
    }
    selected = reservoirSample(noImageQuestions, count);
  }

  if (!shouldHaveImages && selected.some(q => q.image)) {
    showToast('Ikosa ryabaye mu guhitamo ibibazo. Gerageza vuba.');
    return [];
  }

  selected.forEach(q => saveUsedQuestionForIP(ip, q.id));
  return shuffleArray(selected);
}

function reservoirSample(arr, k) {
  if (k <= 0) return [];
  const n = arr.length;
  if (k >= n) return shuffleArray([...arr]).slice(0, k);
  const reservoir = arr.slice(0, k);
  for (let i = k; i < n; i++) {
    const j = Math.floor(Math.random() * (i + 1));
    if (j < k) reservoir[j] = arr[i];
  }
  return reservoir;
}

function startTimer() {
  const storedKey = `quizEndTime_${currentIP || 'unknown'}`;
  const savedEnd = safeLocalStorage(storedKey, 'get');
  
  if (savedEnd) {
    quizEndTime = new Date(savedEnd).getTime();
    timeLeft = Math.max(Math.floor((quizEndTime - Date.now()) / 1000), 0);
  } else {
    quizEndTime = Date.now() + timeLeft * 1000;
    safeLocalStorage(storedKey, 'set', new Date(quizEndTime).toISOString());
  }

  updateTimerDisplay();
  timerId = setInterval(() => {
    if (quizEndTime) {
      timeLeft = Math.max(Math.floor((quizEndTime - Date.now()) / 1000), 0);
      updateTimerDisplay();
    }
    if (timeLeft <= 0) {
      clearInterval(timerId); timerId = null;
      endQuiz();
      return;
    }
    if (timeLeft <= 60 && !warnedLastMinute) {
      warnedLastMinute = true;
      showToast('Hasigaye umunota umwe ngo ikizamini kirangire!');
      playAlertSound();
    }
  }, 1000);
}

function updateTimerDisplay() {
  const safeTimerEl = safeElementAccess(timerEl);
  const minutes = Math.floor(timeLeft / 60);
  const seconds = timeLeft % 60;
  safeTimerEl.textContent = `Igihe: ${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
}

function stopTimer() {
  if (timerId) { clearInterval(timerId); timerId = null; }
  stopDevPoll();
}

function shuffleArray(array) {
  const arr = [...array];
  for (let i = arr.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [arr[i], arr[j]] = [arr[j], arr[i]];
  }
  return arr;
}

function updateProgress() {
  const safeProgressBar = safeElementAccess(progressBar);
  if (questions.length === 0) return;
  safeProgressBar.style.width = `${((currentIndex + 1) / questions.length) * 100}%`;
  safeProgressBar.textContent = `${currentIndex + 1}/${questions.length}`;
}

function showQuestion() {
  if (questions.length === 0 || currentIndex >= questions.length || currentIndex < 0) {
    resetQuizToHome(); return;
  }
  const q = questions[currentIndex];
  const safeQuestionText = safeElementAccess(questionText);
  const safeImageContainer = safeElementAccess(imageContainer);
  const safeOptionsForm = safeElementAccess(optionsForm);
  const safePrevBtn = safeElementAccess(prevBtn);
  const safeNextBtn = safeElementAccess(nextBtn);
  const safeSubmitBtn = safeElementAccess(submitBtn);

  safeQuestionText.textContent = `${currentIndex + 1}. ${q.question}`;
  safeImageContainer.innerHTML = q.image ? `<img src="${q.image}" class="question-img" onerror="this.style.display='none'">` : '';

  const inputs = safeOptionsForm.querySelectorAll('input[type="radio"]');
  const labels = safeOptionsForm.querySelectorAll('label');

  inputs.forEach((input, idx) => {
    const key = ['a', 'b', 'c', 'd'][idx];
    input.value = key;
    
    const optionText = document.getElementById(`option-${key}`);
    safeElementAccess(optionText).textContent = q[key] || '';
    
    const isSelected = selectedAnswers[currentIndex] === key;
    input.checked = isSelected;
    
    if (labels[idx]) {
      labels[idx].style.backgroundColor = isSelected ? '#e0e0e0' : '';
    }
  });

  safePrevBtn.disabled = currentIndex === 0;
  safeNextBtn.disabled = false;

  if (currentIndex === questions.length - 1) {
    safeNextBtn.classList.add('hidden');
    safeSubmitBtn.classList.remove('hidden');
  } else {
    safeNextBtn.classList.remove('hidden');
    safeSubmitBtn.classList.add('hidden');
  }

  // === HIDE NAV BUTTONS UNTIL ANSWER IS SELECTED ===
  if (selectedAnswers[currentIndex]) {
    safeElementAccess(navButtons).style.display = 'flex';
  } else {
    safeElementAccess(navButtons).style.display = 'none';
  }

  // Scroll to top of the quiz screen so they read the new question
  safeElementAccess(quizScreen).scrollTop = 0;
  updateProgress();
}

function setupOptionListeners() {
  const inputs = safeElementAccess(optionsForm).querySelectorAll('input[type="radio"]');
  inputs.forEach((input) => {
    input.addEventListener('change', () => {
      if (currentIndex >= 0 && currentIndex < selectedAnswers.length) {
        selectedAnswers[currentIndex] = input.value;
        const labels = safeElementAccess(optionsForm).querySelectorAll('label');
        labels.forEach((label, i) => {
          label.style.backgroundColor = selectedAnswers[currentIndex] === input.value && i === ['a', 'b', 'c', 'd'].indexOf(input.value) ? '#e0e0e0' : '';
        });
        
        // === SHOW NAV BUTTONS ONCE AN ANSWER IS SELECTED ===
        safeElementAccess(navButtons).style.display = 'flex';

        safeLocalStorage(`quizState_${currentIP || 'unknown'}`, 'set', {
          questions: questions,
          selectedAnswers: selectedAnswers,
          currentIndex: currentIndex,
          quizEndTime: quizEndTime
        });
      }
      lastActivity = Date.now();
    });
  });
}
setupOptionListeners();

// === STRICT NEXT BUTTON LOGIC (Cannot Skip) ===
function nextQuestion() {
  // Check if the current question has an answer
  if (!selectedAnswers[currentIndex]) {
    showToast('Nyamuneka hitamo igisubizo mbere yuko ukomeza! (Please select an answer before proceeding!)');
    return;
  }

  if (currentIndex < questions.length - 1) { 
    currentIndex++; 
    showQuestion(); 
  } else {
    endQuiz();
  }
  lastActivity = Date.now();
  
  safeLocalStorage(`quizState_${currentIP || 'unknown'}`, 'set', {
    questions: questions,
    selectedAnswers: selectedAnswers,
    currentIndex: currentIndex,
    quizEndTime: quizEndTime
  });
}

function prevQuestion() {
  if (currentIndex > 0) { currentIndex--; showQuestion(); }
  lastActivity = Date.now();
  
  safeLocalStorage(`quizState_${currentIP || 'unknown'}`, 'set', {
    questions: questions,
    selectedAnswers: selectedAnswers,
    currentIndex: currentIndex,
    quizEndTime: quizEndTime
  });
}

function resetQuizToHome() {
  stopTimer();
  safeLocalStorage(`quizEndTime_${currentIP || 'unknown'}`, 'set', null);
  safeLocalStorage(`quizState_${currentIP || 'unknown'}`, 'set', null);

  questions = []; selectedAnswers = []; currentIndex = 0; timeLeft = 20 * 60;
  warnedLastMinute = false; quizEndTime = null; devToolsDetected = false;
  hiddenTabWarned = false; inactivityWarned = false; minimizeDetected = false;
  wasHidden = false; lastActivity = Date.now();
  fullscreenExitStrikes = 0; hasEverEnteredFullscreen = false;

  safeElementAccess(quizScreen).classList.add('hidden');
  safeElementAccess(resultScreen).classList.add('hidden');
  safeElementAccess(reviewScreen).classList.add('hidden');
  safeElementAccess(homeScreen).classList.remove('hidden');

  const banner = document.getElementById('fs-banner');
  if (banner) banner.remove();

  exitFullscreen();
  fullscreenEnabled = false;
  window.onpopstate = null;

  if (typeof window.showFooter === 'function') window.showFooter();
  showToast('Ikizamini kirahari. Kanda "Tangira" kugirango urongere utangire.');
}

function endQuiz() {
  stopTimer();
  safeLocalStorage(`quizEndTime_${currentIP || 'unknown'}`, 'set', null);
  safeLocalStorage(`quizState_${currentIP || 'unknown'}`, 'set', null);

  safeElementAccess(quizScreen).classList.add('hidden');
  safeElementAccess(homeScreen).classList.add('hidden');
  safeElementAccess(reviewScreen).classList.add('hidden');
  safeElementAccess(resultScreen).classList.remove('hidden');

  let score = 0;
  if (selectedAnswers.length === questions.length) {
    score = selectedAnswers.reduce((acc, ans, idx) => {
      const q = questions[idx];
      return acc + (q && q.answer && ans === q.answer ? 1 : 0);
    }, 0);
  }

  incrementCompletedExamCount(currentIP);

  safeElementAccess(scoreEl).textContent = `${score}/${questions.length}`;
  const passed = score >= questions.length * 0.6;
  safeElementAccess(passMessage).textContent = passed ? 'Watsinze' : 'Watsinzwe';
  safeElementAccess(passMessage).style.color = passed ? 'green' : 'red';

  window.onpopstate = () => {
    if (!safeElementAccess(resultScreen).classList.contains('hidden')) history.go(1);
  };

  const banner = document.getElementById('fs-banner');
  if (banner) banner.remove();

  exitFullscreen();
  fullscreenEnabled = false;
  hasEverEnteredFullscreen = false;

  if (typeof window.showFooter === 'function') window.showFooter();
}

function reviewAnswers() {
  safeElementAccess(resultScreen).classList.add('hidden');
  safeElementAccess(homeScreen).classList.add('hidden');
  safeElementAccess(quizScreen).classList.add('hidden');
  safeElementAccess(reviewScreen).classList.remove('hidden');

  const safeReviewContainer = safeElementAccess(reviewContainer);
  safeReviewContainer.innerHTML = '';
  const fragment = document.createDocumentFragment();

  questions.forEach((q, idx) => {
    const block = document.createElement('div');
    block.className = 'question-block';
    block.innerHTML = `<h3>${idx + 1}. ${q.question}</h3>` +
      (q.image ? `<img src="${q.image}" class="question-img" onerror="this.style.display='none'">` : '');

    ['a', 'b', 'c', 'd'].forEach((key) => {
      if (!q[key]) return;
      let style = '', mark = '';
      if (key === q.answer) style += 'color:green;font-weight:bold;';
      if (key === selectedAnswers[idx] && key !== q.answer) style += 'color:red;';
      if (key === selectedAnswers[idx]) mark = key === q.answer ? 'Correct' : 'Wrong';
      else if (key === q.answer && selectedAnswers[idx] !== key) mark = 'Correct';
      
      block.innerHTML += `<p style="${style}">${key.toUpperCase()}. ${q[key]}${mark}</p>`;
    });
    fragment.appendChild(block);
  });

  const backBtn = document.createElement('button');
  backBtn.textContent = 'Home';
  backBtn.addEventListener('click', returnToHome);
  fragment.appendChild(backBtn);

  safeReviewContainer.appendChild(fragment);
  if (typeof window.showFooter === 'function') window.showFooter();
}

function returnToHome() {
  safeElementAccess(reviewScreen).classList.add('hidden');
  safeElementAccess(quizScreen).classList.add('hidden');
  safeElementAccess(resultScreen).classList.add('hidden');
  safeElementAccess(homeScreen).classList.remove('hidden');

  questions = []; selectedAnswers = []; currentIndex = 0; timeLeft = 20 * 60;
  quizEndTime = null; fullscreenExitStrikes = 0; hasEverEnteredFullscreen = false;
  safeLocalStorage(`quizEndTime_${currentIP || 'unknown'}`, 'set', null);
  safeLocalStorage(`quizState_${currentIP || 'unknown'}`, 'set', null);

  window.onpopstate = null;
  if (typeof window.showFooter === 'function') window.showFooter();
}

async function startQuiz() {
  if (authorizedPassword) {
    const pwdKey = `passwordTime_${currentIP || 'unknown'}`;
    const pwdTime = safeLocalStorage(pwdKey, 'get');
    if (!pwdTime || (Date.now() - pwdTime) > 6 * 60 * 60 * 1000) {
      const pwd = prompt('Shyiramo ijambo ry\'ibanga kugirango utangire ikizamini:');
      if (pwd !== authorizedPassword) {
        showToast('Ijambo ry\'ibanga ritariho cyangwa si ryo!');
        return;
      }
      safeLocalStorage(pwdKey, 'set', Date.now());
    }
  }

  if (!questionsPool.length) {
    await loadQuestions();
    if (!questionsPool.length) { showToast('Ibibazo ntibiruzura. Tegereza gato.'); return; }
  }

  currentIP = await getIP();
  
  // --- RESTORE EXAM STATE IF MINIMIZED/CLOSED AND RETURNED ---
  const savedState = safeLocalStorage(`quizState_${currentIP}`, 'get');
  if (savedState && savedState.quizEndTime && Date.now() < savedState.quizEndTime) {
    questions = savedState.questions;
    selectedAnswers = savedState.selectedAnswers;
    currentIndex = savedState.currentIndex;
    quizEndTime = savedState.quizEndTime;
    timeLeft = Math.max(Math.floor((quizEndTime - Date.now()) / 1000), 0);
    
    safeElementAccess(homeScreen).classList.add('hidden');
    safeElementAccess(resultScreen).classList.add('hidden');
    safeElementAccess(reviewScreen).classList.add('hidden');
    safeElementAccess(quizScreen).classList.remove('hidden');

    requestFullscreen();
    fullscreenEnabled = true;
    setTimeout(() => { if (isFullscreenActive()) hasEverEnteredFullscreen = true; }, 500);
    
    showQuestion();
    startTimer();
    startDevPoll();
    if (typeof window.hideFooter === 'function') window.hideFooter();
    
    showToast('Murakaza neza. Ikizamini cyawe cyakomeje aho wari ugeze!');
    return;
  }

  // --- FRESH START ---
  let currentIteration = (safeLocalStorage(`iterationCount_${currentIP}`, 'get') || 0) + 1;
  safeLocalStorage(`iterationCount_${currentIP}`, 'set', currentIteration);

  questions = await getExamSet(questionsPool, 20, currentIP, currentIteration);
  if (!questions || questions.length === 0) return; // Will stop here if overlay was shown

  selectedAnswers = new Array(questions.length).fill(null);
  currentIndex = 0; timeLeft = 20 * 60;
  warnedLastMinute = false; devToolsDetected = false; hiddenTabWarned = false;
  inactivityWarned = false; minimizeDetected = false; wasHidden = false;
  lastActivity = Date.now();
  fullscreenExitStrikes = 0;
  hasEverEnteredFullscreen = false;

  safeElementAccess(homeScreen).classList.add('hidden');
  safeElementAccess(resultScreen).classList.add('hidden');
  safeElementAccess(reviewScreen).classList.add('hidden');
  safeElementAccess(quizScreen).classList.remove('hidden');

  requestFullscreen();
  fullscreenEnabled = true;
  
  setTimeout(() => {
    if (isFullscreenActive()) hasEverEnteredFullscreen = true;
  }, 500);

  alertAudio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVpGn+DyvmwhBjiR5/DMeSwFJHfH8N2QQAoUXrTp66hVFApGn+Dyvmwh');

  showQuestion();
  startTimer();
  startDevPoll();

  if (typeof window.hideFooter === 'function') window.hideFooter();
}

function attachEventListener(el, event, handler) {
  const safeEl = safeElementAccess(el);
  if (safeEl && safeEl.addEventListener) safeEl.addEventListener(event, handler);
}

attachEventListener(startBtn, 'click', startQuiz);
attachEventListener(nextBtn, 'click', nextQuestion);
attachEventListener(prevBtn, 'click', prevQuestion);
attachEventListener(submitBtn, 'click', endQuiz);
attachEventListener(restartBtn, 'click', () => location.reload());
attachEventListener(reviewBtn, 'click', reviewAnswers);
attachEventListener(homeBtn, 'click', returnToHome);

window.addEventListener('beforeunload', () => {
  stopTimer();
  exitFullscreen();
});