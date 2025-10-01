/* Complete app.js for Netlify (no backend) with client-side 60-minute question no-reuse per IP using localStorage, fixed options (a, b, c, d) in original order, and random selection of 20 questions. Optimized for 2000+ questions. Removed attempt limit to allow unlimited quizzes. */

// =================== DOM ELEMENTS ===================
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

const timerEl = document.getElementById('timer');
const questionText = document.getElementById('question-text');
const imageContainer = document.getElementById('image-container');
const optionsForm = document.getElementById('options-form');
const scoreEl = document.getElementById('score');
const passMessage = document.getElementById('pass-message');
const reviewContainer = document.getElementById('review-container');
const progressBar = document.getElementById('progress-bar');

// =================== STATE ===================
let questionsPool = [];
let questions = [];
let currentIndex = 0;
let selectedAnswers = [];
let timerId = null;
let timeLeft = 20 * 60; // 20 minutes
let warnedLastMinute = false;
let quizEndTime = null; // Fixed end time
let lastActivity = Date.now(); // For inactivity detection
let inactivityWarned = false;
let fullscreenEnabled = false;
let currentIP = null; // Detected IP address
const authorizedPassword = ''; // Password to start quiz
const ENCRYPTION_KEY = 'quiz_secure_key_2025'; // Key for localStorage obfuscation

// =================== UTILITY FUNCTIONS ===================
async function getIP() {
  try {
    const res = await fetch('https://api.ipify.org?format=json');
    if (!res.ok) throw new Error('Failed to fetch IP');
    const data = await res.json();
    safeLocalStorage('fallbackIP', 'set', data.ip);
    return data.ip;
  } catch (err) {
    console.error('IP detection failed:', err);
    const fallback = safeLocalStorage('fallbackIP', 'get') || 'fallback_' + Math.random().toString(36).slice(2);
    safeLocalStorage('fallbackIP', 'set', fallback);
    return fallback;
  }
}

function getUsedQuestionsForIP(ip) {
  const data = safeLocalStorage('usedQuestionsByIP', 'get') || {};
  const ipData = data[ip] || [];
  const now = Date.now();
  const fresh = ipData.filter(q => now - q.time < 3600000 && q.id);
  data[ip] = fresh;
  safeLocalStorage('usedQuestionsByIP', 'set', data);
  return new Set(fresh.map(q => q.id)); // Use Set for O(1) lookup
}

function saveUsedQuestionForIP(ip, questionId) {
  if (!questionId) return;
  const data = safeLocalStorage('usedQuestionsByIP', 'get') || {};
  if (!data[ip]) data[ip] = [];
  const now = Date.now();
  const ipData = data[ip];
  if (!ipData.some(q => q.id === questionId && now - q.time < 3600000)) {
    ipData.push({ id: questionId, time: now });
    data[ip] = ipData;
    safeLocalStorage('usedQuestionsByIP', 'set', data);
  }
}

function safeLocalStorage(key, operation = 'get', value = null) {
  try {
    const obfuscatedKey = btoa(key + ENCRYPTION_KEY);
    if (operation === 'set') {
      localStorage.setItem(obfuscatedKey, btoa(JSON.stringify(value)));
      return true;
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

// Periodic cleanup of localStorage
setInterval(() => {
  const now = Date.now();
  const usedData = safeLocalStorage('usedQuestionsByIP', 'get') || {};
  Object.keys(usedData).forEach(ip => {
    usedData[ip] = usedData[ip].filter(q => now - q.time < 3600000 && q.id);
    if (usedData[ip].length === 0) delete usedData[ip];
  });
  safeLocalStorage('usedQuestionsByIP', 'set', usedData);
}, 600000); // Every 10 minutes

// =================== SECURITY ===================
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
      console.log('Dev tools detected, ending quiz');
      alert('Ibikoresho bya developer byabonye. Ikizamini ryangiriye!');
      endQuiz();
    }
  }
}
let devPollId = null;
function startDevPoll() {
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
    console.log('Fullscreen exited, ending quiz');
    alert('Ntushobora gusohoka kuri fullscreen. Ikizamini ryangiriye!');
    endQuiz();
  }
});
document.addEventListener('webkitfullscreenchange', () => {
  if (!document.webkitFullscreenElement && fullscreenEnabled) {
    console.log('Fullscreen exited (webkit), ending quiz');
    alert('Ntushobora gusohoka kuri fullscreen. Ikizamini ryangiriye!');
    endQuiz();
  }
});
document.addEventListener('mozfullscreenchange', () => {
  if (!document.mozFullScreenElement && fullscreenEnabled) {
    console.log('Fullscreen exited (moz), ending quiz');
    alert('Ntushobora gusohoka kuri fullscreen. Ikizamini ryangiriye!');
    endQuiz();
  }
});
document.addEventListener('MSFullscreenChange', () => {
  if (!document.msFullscreenElement && fullscreenEnabled) {
    console.log('Fullscreen exited (ms), ending quiz');
    alert('Ntushobora gusohoka kuri fullscreen. Ikizamini ryangiriye!');
    endQuiz();
  }
});

let hiddenTabWarned = false;
document.addEventListener('visibilitychange', () => {
  if (!quizScreen || quizScreen.classList.contains('hidden')) return;
  if (document.hidden) {
    lastActivity = Date.now();
  } else {
    if (quizEndTime) {
      const elapsed = Math.floor((quizEndTime - Date.now()) / 1000);
      timeLeft = Math.max(elapsed, 0);
      updateTimerDisplay();
      if (timeLeft <= 0) {
        console.log('Timer expired on tab switch, ending quiz');
        endQuiz();
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
    alert('Ntabwo uri mu ikizamini? Ikizamini kirangira mu munota 1!');
    setTimeout(() => {
      if (Date.now() - lastActivity > 180000) {
        console.log('Inactivity timeout, ending quiz');
        endQuiz();
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

// =================== LOAD QUESTIONS ===================
async function loadQuestions() {
  try {
    const res = await fetch('questions.json');
    if (!res.ok) throw new Error(`Failed to load questions.json: ${res.statusText}`);
    const externalQuestions = await res.json();
    if (!Array.isArray(externalQuestions) || externalQuestions.length === 0) {
      throw new Error('questions.json is empty or not an array');
    }

    questionsPool = externalQuestions
      .filter(q => q.id && q.question && ['a', 'b', 'c', 'd'].every(key => q[key]) && ['a', 'b', 'c', 'd'].includes((q.answer || '').toLowerCase()))
      .map(q => ({
        id: q.id,
        question: q.question,
        image: q.image ? `images/${q.image}` : null,
        a: q.a,
        b: q.b,
        c: q.c,
        d: q.d,
        answer: (q.answer || '').toLowerCase()
      }));

    if (questionsPool.length < 20) {
      throw new Error(`Insufficient questions in questions.json: ${questionsPool.length}/20 required`);
    }

    safeElementAccess(startBtn).disabled = false;
    console.log(`Loaded ${questionsPool.length} valid questions.`);
  } catch (err) {
    console.error('Failed to load questions:', err);
    alert('Error: Questions could not be loaded. Check console.');
    safeElementAccess(startBtn).disabled = true;
  }
}
loadQuestions();

// =================== QUESTION HISTORY (60 min cache) ===================
async function getExamSet(allQuestions, count = 20, maxImages = 3, ip) {
  const used = getUsedQuestionsForIP(ip);
  let available = allQuestions.filter(q => !used.has(q.id));

  if (available.length < count) {
    alert(`Nta bibazo bihagije bishya bihari kuri iyi IP/device (${available.length}/${count}). Gerageza nyuma y'amasaha 1.`);
    return [];
  }

  const imageQuestions = available.filter(q => q.image);
  const noImageQuestions = available.filter(q => !q.image);

  let selected = [];

  // Select 0–3 image questions randomly
  let numImages = 0;
  if (imageQuestions.length > 0) {
    const maxPossible = Math.min(maxImages, imageQuestions.length, count);
    numImages = Math.floor(Math.random() * (maxPossible + 1));
    if (numImages > 0) {
      const shuffledImages = shuffleArray(imageQuestions);
      selected = selected.concat(shuffledImages.slice(0, numImages));
    }
  }

  // Fill remaining with non-image questions
  let remainingCount = count - selected.length;
  if (remainingCount > 0) {
    const shuffledNoImages = shuffleArray(noImageQuestions);
    selected = selected.concat(shuffledNoImages.slice(0, remainingCount));
  }

  // Ensure exactly 20 questions
  if (selected.length < count) {
    const needed = count - selected.length;
    const remainingAvailable = available.filter(q => !selected.some(s => s.id === q.id));
    const shuffledRemaining = shuffleArray(remainingAvailable);
    selected = selected.concat(shuffledRemaining.slice(0, needed));
  }

  if (selected.length !== count) {
    console.error(`Failed to select ${count} questions, got ${selected.length}`);
    alert(`Ikosa ryabaye mu guhitamo ibibazo ${count}. Gerageza vuba.`);
    return [];
  }

  // Save selected question IDs for this IP
  selected.forEach(q => saveUsedQuestionForIP(ip, q.id));

  // Shuffle final set for random question order
  return shuffleArray(selected);
}

// =================== TIMER ===================
function startTimer() {
  const safeTimerEl = safeElementAccess(timerEl);
  if (!safeTimerEl) return;

  const savedEnd = safeLocalStorage('quizEndTime', 'get');
  if (savedEnd) {
    quizEndTime = new Date(savedEnd).getTime();
    const diff = Math.floor((quizEndTime - Date.now()) / 1000);
    timeLeft = Math.max(diff, 0);
  } else {
    quizEndTime = Date.now() + timeLeft * 1000;
    safeLocalStorage('quizEndTime', 'set', new Date(quizEndTime).toISOString());
  }

  updateTimerDisplay();
  timerId = setInterval(() => {
    if (quizEndTime) {
      const elapsed = Math.floor((quizEndTime - Date.now()) / 1000);
      timeLeft = Math.max(elapsed, 0);
      updateTimerDisplay();
    }
    if (timeLeft <= 0) {
      console.log('Timer expired, ending quiz');
      clearInterval(timerId);
      timerId = null;
      endQuiz();
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

// =================== SHUFFLE ===================
function shuffleArray(array) {
  if (!Array.isArray(array) || array.length === 0) return [];
  const arr = [...array];
  for (let i = arr.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [arr[i], arr[j]] = [arr[j], arr[i]];
  }
  return arr;
}

// =================== PROGRESS ===================
function updateProgress() {
  const safeProgressBar = safeElementAccess(progressBar);
  if (!safeProgressBar || questions.length === 0) return;
  const prog = ((currentIndex + 1) / questions.length) * 100;
  safeProgressBar.style.width = `${prog}%`;
  safeProgressBar.textContent = `${currentIndex + 1}/${questions.length}`;
}

// =================== SHOW QUESTION ===================
function showQuestion() {
  if (questions.length === 0 || currentIndex >= questions.length || currentIndex < 0) {
    console.error('Invalid question index:', currentIndex, 'Questions length:', questions.length);
    alert('Ikosa ryabaye mu kugaragaza ibibazo. Ikizamini ryahagaritswe.');
    endQuiz();
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
    alert('Ikosa ryabaye mu kugaragaza ibyavugwa. Ikizamini ryahagaritswe.');
    endQuiz();
    return;
  }

  inputs.forEach((input, idx) => {
    const key = optionKeys[idx];
    input.value = key; // Store option key (a, b, c, d)
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

// =================== SELECT OPTION ===================
function setupOptionListeners() {
  const safeOptionsForm = safeElementAccess(optionsForm);
  if (!safeOptionsForm) return;

  const inputs = safeOptionsForm.querySelectorAll('input[type="radio"]');
  inputs.forEach((input) => {
    input.addEventListener('change', () => {
      if (currentIndex >= 0 && currentIndex < selectedAnswers.length) {
        selectedAnswers[currentIndex] = input.value; // Store option key (a, b, c, d)
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

// =================== NAVIGATION ===================
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

// =================== END QUIZ ===================
function endQuiz() {
  stopTimer();
  safeLocalStorage('quizEndTime', 'set', null);
  const safeQuizScreen = safeElementAccess(quizScreen);
  const safeResultScreen = safeElementAccess(resultScreen);
  safeQuizScreen.classList.add('hidden');
  safeResultScreen.classList.remove('hidden');

  let score = 0;
  if (selectedAnswers.length === questions.length) {
    score = selectedAnswers.reduce((acc, ans, idx) => {
      const q = questions[idx];
      if (!q || !q.answer) return acc;
      return acc + (ans === q.answer ? 1 : 0);
    }, 0);
  }

  const safeScoreEl = safeElementAccess(scoreEl);
  safeScoreEl.textContent = `${score}/${questions.length}`;
  const safePassMessage = safeElementAccess(passMessage);
  const passed = score >= questions.length * 0.6;
  safePassMessage.textContent = passed ? 'Watsinze 🎉' : 'Watsinzwe ❌';
  safePassMessage.style.color = passed ? 'green' : 'red';

  window.onpopstate = e => {
    if (!safeResultScreen.classList.contains('hidden')) {
      history.go(1);
    }
  };

  exitFullscreen();
  fullscreenEnabled = false;
}

// =================== REVIEW ===================
function reviewAnswers() {
  const safeResultScreen = safeElementAccess(resultScreen);
  const safeReviewScreen = safeElementAccess(reviewScreen);
  safeResultScreen.classList.add('hidden');
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
        mark = key === q.answer ? ' ✅' : ' ❌';
      } else if (key === q.answer && selectedAnswers[idx] !== key) {
        mark = ' ✅';
      }
      block.innerHTML += `<p style="${style}">${key.toUpperCase()}. ${q[key]}${mark}</p>`;
    });

    fragment.appendChild(block);
  });

  safeReviewContainer.appendChild(fragment);
}

// =================== START QUIZ ===================
async function startQuiz() {
  const pwd = prompt('Shyiramo ijambo ry\'ibanga kugirango utangire ikizamini:');
  if (pwd !== authorizedPassword) {
    alert('Ntushobora gutangira ikizamini. Ijambo ry\'ibanga ntiririho!');
    return;
  }

  if (!questionsPool.length) {
    await loadQuestions();
    if (!questionsPool.length) {
      alert('Ibibazo ntibiruzura. Tegereza gato.');
      return;
    }
  }

  currentIP = await getIP();
  console.log(`Detected IP: ${currentIP}`);

  questions = await getExamSet(questionsPool, 20, 3, currentIP);
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
  lastActivity = Date.now();

  const safeHomeScreen = safeElementAccess(homeScreen);
  const safeQuizScreen = safeElementAccess(quizScreen);
  safeHomeScreen.classList.add('hidden');
  safeQuizScreen.classList.remove('hidden');

  requestFullscreen();
  fullscreenEnabled = true;

  alertAudio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBjiR5/DMeSwFJHfH8N2QQAoUXrTp66hVFApGn+Dyvmwh');

  showQuestion();
  startTimer();
  startDevPoll();
}

// =================== EVENT LISTENERS ===================
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

window.addEventListener('beforeunload', () => {
  stopTimer();
  exitFullscreen();
});