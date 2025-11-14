// ---------------------------------------------------------------------
//  ORIGINAL CODE (unchanged) – DOM references, utilities, anti-cheat, etc.
// ---------------------------------------------------------------------
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
let currentIP = null;
const authorizedPassword = 'exam2025'; // Password required to start quiz
const ENCRYPTION_KEY = 'quiz_secure_key_2025';
const MIN_QUESTIONS_REQUIRED = 20;

// === NEW: Track completed exams per IP ===
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
    console.warn('IP detection via ipify failed, using deterministic fallback. Error:', err);
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
        localStorage.setItem(obfuscatedKey, btoa(JSON.stringify(value)));
        return true;
      }
    } else {
      const data = localStorage.getItem(obfuscatedKey);
      return data ? JSON.parse(atob(data)) : null;
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
  } catch (err) {
    console.error('Periodic cleanup error:', err);
  }
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
  if (e.persisted) {
    window.location.reload();
  }
});

let devToolsDetected = false;
function detectDevTools() {
  if (!quizScreen || quizScreen.classList.contains('hidden')) return;
  const threshold = 100;
  if (window.outerHeight - window.innerHeight > threshold || window.outerWidth - window.innerWidth > threshold) {
    if (!devToolsDetected) {
      devToolsDetected = true;
      console.log('Dev tools detected, resetting quiz (no permanent dropout)');
      alert('Ibikoresho bya developer byabonye. Ikizamini kirongera gutangira!');
      resetQuizToHome();
    }
  }
}
let devPollId = null;
function startDevPoll() {
  if (devPollId) return;
  devPollId = setInterval(detectDevTools, 500);
}
function stopDevPoll() {
  if (devPollId) {
    clearInterval(devPollId);
    devPollId = null;
  }
}

function requestFullscreen() {
  try {
    if (document.documentElement.requestFullscreen) {
      document.documentElement.requestFullscreen();
    } else if (document.documentElement.mozRequestFullScreen) {
      document.documentElement.mozRequestFullScreen();
    } else if (document.documentElement.webkitRequestFullscreen) {
      document.documentElement.webkitRequestFullscreen();
    } else if (document.documentElement.msRequestFullscreen) {
      document.documentElement.msRequestFullscreen();
    }
  } catch (err) {
    console.error('Fullscreen request failed:', err);
  }
}

function exitFullscreen() {
  try {
    if (document.exitFullscreen) {
      document.exitFullscreen();
    } else if (document.mozCancelFullScreen) {
      document.mozCancelFullScreen();
    } else if (document.webkitExitFullscreen) {
      document.webkitExitFullscreen();
    } else if (document.msExitFullscreen) {
      document.msExitFullscreen();
    }
  } catch (err) {
    console.error('Fullscreen exit failed:', err);
  }
}

document.addEventListener('fullscreenchange', () => {
  if (!document.fullscreenElement && fullscreenEnabled) {
    console.log('Fullscreen exited, resetting quiz (no dropout)');
    alert('Ntushobora gusohoka kuri fullscreen. Ikizamini kirongera!');
    resetQuizToHome();
  }
});
document.addEventListener('webkitfullscreenchange', () => {
  if (!document.webkitFullscreenElement && fullscreenEnabled) {
    console.log('Fullscreen exited (webkit), resetting quiz');
    alert('Ntushobora gusohoka kuri fullscreen. Ikizamini kirongera!');
    resetQuizToHome();
  }
});
document.addEventListener('mozfullscreenchange', () => {
  if (!document.mozFullScreenElement && fullscreenEnabled) {
    console.log('Fullscreen exited (moz), resetting quiz');
    alert('Ntushobora gusohoka kuri fullscreen. Ikizamini kirongera!');
    resetQuizToHome();
  }
});
document.addEventListener('MSFullscreenChange', () => {
  if (!document.msFullscreenElement && fullscreenEnabled) {
    console.log('Fullscreen exited (ms), resetting quiz');
    alert('Ntushobora gusohoka kuri fullscreen. Ikizamini kirongera!');
    resetQuizToHome();
  }
});

let hiddenTabWarned = false;
let minimizeDetected = false;
document.addEventListener('visibilitychange', () => {
  if (!quizScreen || quizScreen.classList.contains('hidden')) return;
  if (document.hidden) {
    if (!minimizeDetected) {
      minimizeDetected = true;
      console.log('Window minimized or tab switched, resetting quiz');
      alert('Ikizamini cyahagaritswe kubera guhisha idirishya! Kirongera gutangira.');
      resetQuizToHome();
    }
  } else {
    if (quizEndTime) {
      const elapsed = Math.floor((quizEndTime - Date.now()) / 1000);
      timeLeft = Math.max(elapsed, 0);
      updateTimerDisplay();
      if (timeLeft <= 0) {
        console.log('Timer expired on tab switch, resetting quiz');
        resetQuizToHome();
      }
    }
    const absenceTime = Date.now() - lastActivity;
    if (absenceTime > 30000 && !hiddenTabWarned) {
      hiddenTabWarned = true;
      alert('Utangiye ikizamini mu bice. Gerageza gukora neza!');
    }
  }
});

document.addEventListener('mousemove', () => { lastActivity = Date.now(); inactivityCheck(); });
document.addEventListener('keydown', () => { lastActivity = Date.now(); inactivityCheck(); });
function inactivityCheck() {
  if (!quizScreen || quizScreen.classList.contains('hidden')) return;
  const inactiveTime = Date.now() - lastActivity;
  if (inactiveTime > 120000 && !inactivityWarned) {
    inactivityWarned = true;
    alert('Ntabwo uri mu ikizamini? Ikizamini kirongera gutangira mu munota 1!');
    setTimeout(() => {
      if (Date.now() - lastActivity > 180000) {
        console.log('Inactivity timeout, resetting quiz');
        resetQuizToHome();
      }
    }, 60000);
  }
}

let alertAudio = null;
function playAlertSound() {
  if (alertAudio) {
    alertAudio.play().catch(() => {});
  }
}

async function loadQuestions() {
  try {
    const res = await fetch('questions.json');
    if (!res.ok) throw new Error(`Failed to load questions.json: ${res.statusText}`);
    const externalQuestions = await res.json();
    if (!Array.isArray(externalQuestions) || externalQuestions.length === 0) {
      throw new Error('questions.json is empty or not an array');
    }

    questionsPool = externalQuestions
      .filter(q => q && q.id && q.question && ['a', 'b', 'c', 'd'].every(key => q[key]) && ['a', 'b', 'c', 'd'].includes((q.answer || '').toLowerCase()))
      .map(q => ({
        id: String(q.id),
        question: q.question,
        image: q.image ? `images/${q.image}` : null,
        a: q.a,
        b: q.b,
        c: q.c,
        d: q.d,
        answer: (q.answer || '').toLowerCase()
      }));

    if (questionsPool.length < MIN_QUESTIONS_REQUIRED) {
      throw new Error(`Insufficient questions in questions.json: ${questionsPool.length}/${MIN_QUESTIONS_REQUIRED} required`);
    }

    const imageQuestionsCount = questionsPool.filter(q => q.image).length;
    if (imageQuestionsCount < 3) {
      throw new Error(`Insufficient image questions in questions.json: ${imageQuestionsCount}/3 required`);
    }

    safeElementAccess(startBtn).disabled = false;
    console.log(`Loaded ${questionsPool.length} valid questions, ${imageQuestionsCount} with images.`);
  } catch (err) {
    console.error('Failed to load questions:', err);
    alert('Error: Questions could not be loaded. Check console.');
    safeElementAccess(startBtn).disabled = true;
  }
}
loadQuestions();

// === FIXED: getExamSet with strict no-image enforcement ===
async function getExamSet(allQuestions, count = 20, ip, iteration = 0) {
  const used = getUsedQuestionsForIP(ip);
  let available = allQuestions.filter(q => !used.has(q.id));

  if (available.length < count) {
    alert(`Nta bibazo bihagije bishya bihari kuri iyi IP/device (${available.length}/${count}). Gerageza nyuma y'amasaha 1.`);
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
      alert(`Nta bibazo bihagije bishya by'amashusho cyangwa bidafite amashusho. Gerageza nyuma y'amasaha 1.`);
      return [];
    }

    selected = selected.concat(reservoirSample(imageQuestions, 3));
    selected = selected.concat(reservoirSample(noImageQuestions, count - 3));
  } else {
    const noImageQuestions = available.filter(q => !q.image);
    if (noImageQuestions.length < count) {
      alert(`Nta bibazo bihagije bishya bidafite amashusho bihari (${noImageQuestions.length}/${count}). Gerageza nyuma y'amasaha 1.`);
      return [];
    }
    selected = reservoirSample(noImageQuestions, count);
  }

  if (!shouldHaveImages && selected.some(q => q.image)) {
    console.error('IMAGE LEAK DETECTED IN NON-IMAGE EXAM!');
    alert('Ikosa ryabaye mu guhitamo ibibazo. Gerageza vuba.');
    return [];
  }

  if (selected.length !== count) {
    console.error(`Failed to select ${count} questions, got ${selected.length}`);
    alert(`Ikosa ryabaye mu guhitamo ibibazo ${count}. Gerageza vuba.`);
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
  const safeTimerEl = safeElementAccess(timerEl);
  if (!safeTimerEl) return;

  const storedKey = `quizEndTime_${currentIP || 'unknown'}`;
  const savedEnd = safeLocalStorage(storedKey, 'get');
  if (savedEnd) {
    quizEndTime = new Date(savedEnd).getTime();
    const diff = Math.floor((quizEndTime - Date.now()) / 1000);
    timeLeft = Math.max(diff, 0);
  } else {
    quizEndTime = Date.now() + timeLeft * 1000;
    safeLocalStorage(storedKey, 'set', new Date(quizEndTime).toISOString());
  }

  updateTimerDisplay();
  timerId = setInterval(() => {
    if (quizEndTime) {
      const elapsed = Math.floor((quizEndTime - Date.now()) / 1000);
      timeLeft = Math.max(elapsed, 0);
      updateTimerDisplay();
    }
    if (timeLeft <= 0) {
      console.log('Timer expired, resetting quiz');
      clearInterval(timerId);
      timerId = null;
      resetQuizToHome();
      return;
    }

    if (timeLeft === 60 && !warnedLastMinute) {
      warnedLastMinute = true;
      alert('Hasigaye umunota umwe ngo ikizamini kirangire!');
      playAlertSound();
    }
  }, 1000);
}

function updateTimerDisplay() {
  const safeTimerEl = safeElementAccess(timerEl);
  if (!safeTimerEl) return;
  const minutes = Math.floor(timeLeft / 60);
  const seconds = timeLeft % 60;
  safeTimerEl.textContent = `Igihe: ${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
}

function stopTimer() {
  if (timerId) {
    clearInterval(timerId);
    timerId = null;
  }
  stopDevPoll();
}

function shuffleArray(array) {
  if (!Array.isArray(array) || array.length === 0) return [];
  const arr = [...array];
  for (let i = arr.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [arr[i], arr[j]] = [arr[j], arr[i]];
  }
  return arr;
}

function updateProgress() {
  const safeProgressBar = safeElementAccess(progressBar);
  if (!safeProgressBar || questions.length === 0) return;
  const prog = ((currentIndex + 1) / questions.length) * 100;
  safeProgressBar.style.width = `${prog}%`;
  safeProgressBar.textContent = `${currentIndex + 1}/${questions.length}`;
}

function showQuestion() {
  if (questions.length === 0 || currentIndex >= questions.length || currentIndex < 0) {
    console.error('Invalid question index:', currentIndex, 'Questions length:', questions.length);
    alert('Ikosa ryabaye mu kugaragaza ibibazo. Ikizamini kirongera.');
    resetQuizToHome();
    return;
  }
  const q = questions[currentIndex];
  const safeQuestionText = safeElementAccess(questionText);
  const safeImageContainer = safeElementAccess(imageContainer);
  const safeOptionsForm = safeElementAccess(optionsForm);
  const safePrevBtn = safeElementAccess(prevBtn);
  const safeNextBtn = safeElementAccess(nextBtn);
  const safeSubmitBtn = safeElementAccess(submitBtn);

  safeQuestionText.textContent = `${currentIndex + 1}. ${q.question}`;

  safeImageContainer.innerHTML = q.image ? `<img src="${q.image}" class="question-img" alt="Question image" loading="lazy" onerror="this.style.display='none'">` : '';

  const optionKeys = ['a', 'b', 'c', 'd'];
  const inputs = safeOptionsForm.querySelectorAll('input[type="radio"]');
  if (inputs.length !== 4) {
    console.error('Expected 4 radio inputs, found:', inputs.length);
    alert('Ikosa ryabaye mu kugaragaza ibyavugwa. Ikizamini kirongera.');
    resetQuizToHome();
    return;
  }

  inputs.forEach((input, idx) => {
    const key = optionKeys[idx];
    input.value = key;
    const optionText = document.getElementById(`option-${key}`);
    const safeOptionText = safeElementAccess(optionText, { textContent: '' });
    safeOptionText.textContent = q[key] || '';
    input.checked = selectedAnswers[currentIndex] === key;
    safeOptionText.style.backgroundColor = selectedAnswers[currentIndex] === key ? '#e0e0e0' : '';
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

  updateProgress();
}

function setupOptionListeners() {
  const safeOptionsForm = safeElementAccess(optionsForm);
  if (!safeOptionsForm) return;

  const inputs = safeOptionsForm.querySelectorAll('input[type="radio"]');
  inputs.forEach((input) => {
    input.addEventListener('change', () => {
      if (currentIndex >= 0 && currentIndex < selectedAnswers.length) {
        selectedAnswers[currentIndex] = input.value;
        const labels = safeOptionsForm.querySelectorAll('label');
        labels.forEach((label, i) => {
          label.style.backgroundColor = selectedAnswers[currentIndex] === input.value && i === ['a', 'b', 'c', 'd'].indexOf(input.value) ? '#e0e0e0' : '';
        });
      }
      lastActivity = Date.now();
      inactivityCheck();
    });
  });
}
setupOptionListeners();

function nextQuestion() {
  if (currentIndex < questions.length - 1) {
    currentIndex++;
    showQuestion();
  } else {
    console.log('Reached last question, ending quiz via next');
    endQuiz();
  }
  lastActivity = Date.now();
}

function prevQuestion() {
  if (currentIndex > 0) {
    currentIndex--;
    showQuestion();
  }
  lastActivity = Date.now();
}

function resetQuizToHome() {
  stopTimer();
  const storedKey = `quizEndTime_${currentIP || 'unknown'}`;
  safeLocalStorage(storedKey, 'set', null);

  questions = [];
  selectedAnswers = [];
  currentIndex = 0;
  timeLeft = 20 * 60;
  warnedLastMinute = false;
  quizEndTime = null;
  devToolsDetected = false;
  hiddenTabWarned = false;
  inactivityWarned = false;
  minimizeDetected = false;
  lastActivity = Date.now();

  const safeQuizScreen = safeElementAccess(quizScreen);
  const safeResultScreen = safeElementAccess(resultScreen);
  const safeReviewScreen = safeElementAccess(reviewScreen);
  const safeHomeScreen = safeElementAccess(homeScreen);

  safeQuizScreen.classList.add('hidden');
  safeResultScreen.classList.add('hidden');
  safeReviewScreen.classList.add('hidden');
  safeHomeScreen.classList.remove('hidden');

  exitFullscreen();
  fullscreenEnabled = false;

  window.onpopstate = null;

  // SHOW FOOTER when returning to home
  if (typeof window.showFooter === 'function') window.showFooter();

  alert('Ikizamini kirahari. Kanda "Tangira" kugirango urongere utangire.');
}

// ---------------------------------------------------------------------
//  NEW: 2-FREE-ATTEMPTS + PAYMENT LOGIC
// ---------------------------------------------------------------------

// 1. Track attempts (localStorage + IP fallback)
function getAttempts() {
  const ip = currentIP || 'unknown';
  const key = `examAttempts_${ip}`;
  return safeLocalStorage(key, 'get') ?? 0;
}
function addAttempt() {
  const ip = currentIP || 'unknown';
  const key = `examAttempts_${ip}`;
  const attempts = (safeLocalStorage(key, 'get') ?? 0) + 1;
  safeLocalStorage(key, 'set', attempts);
  return attempts;
}
function resetAttempts() {
  const ip = currentIP || 'unknown';
  const key = `examAttempts_${ip}`;
  safeLocalStorage(key, 'set', 0);
}

// 2. Check payment status
function isPaidUser() {
  return safeLocalStorage('paidUser', 'get') === 'true';
}

// 3. Show attempts notice (inject into home screen)
function updateAttemptsNotice() {
  let notice = document.getElementById('attempts-notice');
  if (!notice) {
    notice = document.createElement('p');
    notice.id = 'attempts-notice';
    notice.style.margin = '10px 0';
    notice.style.fontWeight = '600';
    notice.style.color = '#d35400';
    const container = homeScreen.querySelector('div[style*="text-align: center"]') || homeScreen;
    container.insertBefore(notice, startBtn);
  }

  if (isPaidUser()) {
    notice.textContent = 'Wishyuye – Ufite ingaruka zisanzwe!';
    notice.style.color = '#27ae60';
  } else {
    const remaining = Math.max(0, 2 - getAttempts());
    if (remaining > 0) {
      notice.textContent = `Ufite ingaruka ${remaining} zisigaye (2 free)`;
    } else {
      notice.textContent = 'Nta ngaruka zisigaye – Wishyure kugira ngo ukomeze!';
      notice.style.color = '#c0392b';
    }
  }
}

// 4. Block start if >2 attempts & not paid
async function startQuiz() {
  // Existing password logic (unchanged)
  if (authorizedPassword) {
    const pwdKey = `passwordTime_${currentIP || 'unknown'}`;
    const pwdTime = safeLocalStorage(pwdKey, 'get');
    const now = Date.now();
    const sixHours = 6 * 60 * 60 * 1000;

    if (!pwdTime || (now - pwdTime) > sixHours) {
      const pwd = prompt('Shyiramo ijambo ry\'ibanga kugirango utangire ikizamini:');
      if (pwd !== authorizedPassword) {
        alert('Ntushobora gutangira ikizamini. Ijambo ry\'ibanga ritariho cyangwa si ryo!');
        return;
      }
      safeLocalStorage(pwdKey, 'set', now);
    }
  }

  // Load questions if needed
  if (!questionsPool.length) {
    await loadQuestions();
    if (!questionsPool.length) {
      alert('Ibibazo ntibiruzura. Tegereza gato.');
      return;
    }
  }

  currentIP = await getIP();
  console.log(`Detected IP: ${currentIP}`);

  // === PAYMENT / ATTEMPTS CHECK ===
  if (!isPaidUser()) {
    const attempts = getAttempts();
    if (attempts >= 2) {
      alert('Wakoze ingaruka 2 za free. Wishyure kugira ngo ukomeze!');
      window.location.href = 'pay.html';
      return;
    }
  }

  // Proceed with quiz (original flow)
  const iterationKey = `iterationCount_${currentIP}`;
  let currentIteration = safeLocalStorage(iterationKey, 'get') || 0;
  currentIteration++;
  safeLocalStorage(iterationKey, 'set', currentIteration);
  console.log(`Starting iteration ${currentIteration} for IP ${currentIP}.`);

  questions = await getExamSet(questionsPool, 20, currentIP, currentIteration);
  if (!questions || questions.length === 0) {
    return;
  }

  selectedAnswers = new Array(questions.length).fill(null);
  currentIndex = 0;
  timeLeft = 20 * 60;
  warnedLastMinute = false;
  devToolsDetected = false;
  hiddenTabWarned = false;
  inactivityWarned = false;
  minimizeDetected = false;
  lastActivity = Date.now();

  const safeHomeScreen = safeElementAccess(homeScreen);
  const safeQuizScreen = safeElementAccess(quizScreen);
  const safeResultScreen = safeElementAccess(resultScreen);
  const safeReviewScreen = safeElementAccess(reviewScreen);

  safeHomeScreen.classList.add('hidden');
  safeResultScreen.classList.add('hidden');
  safeReviewScreen.classList.add('hidden');
  safeQuizScreen.classList.remove('hidden');

  requestFullscreen();
  fullscreenEnabled = true;

  alertAudio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVpGn+DyvmwhBjiR5/DMeSwFJHfH8N2QQAoUXrTp66hVFApGn+Dyvmwh');

  showQuestion();
  startTimer();
  startDevPoll();

  // HIDE FOOTER when entering quiz
  if (typeof window.hideFooter === 'function') window.hideFooter();
}

// 5. On submission → add attempt BEFORE showing result
function endQuiz() {
  stopTimer();
  const storedKey = `quizEndTime_${currentIP || 'unknown'}`;
  safeLocalStorage(storedKey, 'set', null);

  const safeQuizScreen = safeElementAccess(quizScreen);
  const safeResultScreen = safeElementAccess(resultScreen);
  const safeHomeScreen = safeElementAccess(homeScreen);
  const safeReviewScreen = safeElementAccess(reviewScreen);

  safeQuizScreen.classList.add('hidden');
  safeHomeScreen.classList.add('hidden');
  safeReviewScreen.classList.add('hidden');
  safeResultScreen.classList.remove('hidden');

  let score = 0;
  if (selectedAnswers.length === questions.length) {
    score = selectedAnswers.reduce((acc, ans, idx) => {
      const q = questions[idx];
      if (!q || !q.answer) return acc;
      return acc + (ans === q.answer ? 1 : 0);
    }, 0);
  }

  // === ADD ATTEMPT ONLY IF NOT PAID ===
  if (!isPaidUser()) {
    addAttempt();
  }

  incrementCompletedExamCount(currentIP);

  const safeScoreEl = safeElementAccess(scoreEl);
  safeScoreEl.textContent = `${score}/${questions.length}`;
  const safePassMessage = safeElementAccess(passMessage);
  const passed = score >= questions.length * 0.6;
  safePassMessage.textContent = passed ? 'Watsinze' : 'Watsinzwe';
  safePassMessage.style.color = passed ? 'green' : 'red';

  window.onpopstate = e => {
    if (!safeResultScreen.classList.contains('hidden')) {
      history.go(1);
    }
  };

  exitFullscreen();
  fullscreenEnabled = false;

  // SHOW FOOTER when showing result
  if (typeof window.showFooter === 'function') window.showFooter();

  // Update notice on result screen
  updateAttemptsNotice();
}

// ---------------------------------------------------------------------
//  PAYMENT CALLBACK (pay.html calls this on success)
// ---------------------------------------------------------------------
// Call this function from pay.html after successful payment:
window.completePayment = function () {
  safeLocalStorage('paidUser', 'set', 'true');
  resetAttempts();
  alert('Wishyuye neza! Ufite ingaruka zisanzwe!');
  window.location.href = 'index.html';
};

// ---------------------------------------------------------------------
//  REST OF ORIGINAL CODE (unchanged)
// ---------------------------------------------------------------------
function reviewAnswers() {
  const safeResultScreen = safeElementAccess(resultScreen);
  const safeReviewScreen = safeElementAccess(reviewScreen);
  const safeHomeScreen = safeElementAccess(homeScreen);
  const safeQuizScreen = safeElementAccess(quizScreen);

  safeResultScreen.classList.add('hidden');
  safeHomeScreen.classList.add('hidden');
  safeQuizScreen.classList.add('hidden');
  safeReviewScreen.classList.remove('hidden');

  const safeReviewContainer = safeElementAccess(reviewContainer);
  if (!safeReviewContainer) return;
  safeReviewContainer.innerHTML = '';

  const fragment = document.createDocumentFragment();

  questions.forEach((q, idx) => {
    if (!q) return;
    const block = document.createElement('div');
    block.className = 'question-block';

    block.innerHTML = `<h3>${idx + 1}. ${q.question}</h3>` +
      (q.image ? `<img src="${q.image}" class="question-img" alt="Review image" loading="lazy" onerror="this.style.display='none'">` : '');

    const optionKeys = ['a', 'b', 'c', 'd'];
    optionKeys.forEach((key, i) => {
      if (!q[key]) return;
      let style = '';
      let mark = '';
      if (key === q.answer) style += 'color:green;font-weight:bold;';
      if (key === selectedAnswers[idx] && key !== q.answer) style += 'color:red;';
      if (key === selectedAnswers[idx]) {
        mark = key === q.answer ? 'Correct' : 'Wrong';
      } else if (key === q.answer && selectedAnswers[idx] !== key) {
        mark = 'Correct';
      }
      block.innerHTML += `<p style="${style}">${key.toUpperCase()}. ${q[key]}${mark}</p>`;
    });

    fragment.appendChild(block);
  });

  const backBtn = document.createElement('button');
  backBtn.textContent = 'Home';
  backBtn.id = 'review-home-btn';
  backBtn.addEventListener('click', returnToHome);
  fragment.appendChild(backBtn);

  safeReviewContainer.appendChild(fragment);

  if (typeof window.showFooter === 'function') window.showFooter();
}

function returnToHome() {
  const safeReviewScreen = safeElementAccess(reviewScreen);
  const safeHomeScreen = safeElementAccess(homeScreen);
  const safeQuizScreen = safeElementAccess(quizScreen);
  const safeResultScreen = safeElementAccess(resultScreen);

  safeReviewScreen.classList.add('hidden');
  safeQuizScreen.classList.add('hidden');
  safeResultScreen.classList.add('hidden');
  safeHomeScreen.classList.remove('hidden');

  questions = [];
  selectedAnswers = [];
  currentIndex = 0;
  timeLeft = 20 * 60;
  warnedLastMinute = false;
  quizEndTime = null;
  devToolsDetected = false;
  hiddenTabWarned = false;
  inactivityWarned = false;
  minimizeDetected = false;
  lastActivity = Date.now();

  const storedKey = `quizEndTime_${currentIP || 'unknown'}`;
  safeLocalStorage(storedKey, 'set', null);

  window.onpopstate = null;

  if (typeof window.showFooter === 'function') window.showFooter();

  updateAttemptsNotice(); // Refresh notice on home
}

// ---------------------------------------------------------------------
//  EVENT LISTENERS (unchanged)
// ---------------------------------------------------------------------
function attachEventListener(el, event, handler) {
  const safeEl = safeElementAccess(el);
  if (safeEl && safeEl.addEventListener) {
    safeEl.addEventListener(event, handler);
  }
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

// ---------------------------------------------------------------------
//  INITIALIZE ATTEMPTS NOTICE ON PAGE LOAD
// ---------------------------------------------------------------------
document.addEventListener('DOMContentLoaded', () => {
  updateAttemptsNotice();
});