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
const answerMap = { a: 0, b: 1, c: 2, d: 3 };
const authorizedPassword = ''; // Password to start quiz
const ENCRYPTION_KEY = 'quiz_secure_key_2025'; // Simple key for localStorage obfuscation

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

function getUsedQuestionsLocal() {
  const data = safeLocalStorage("usedQuestions", 'get') || [];
  const now = Date.now();
  const fresh = data.filter(q => now - q.time < 3600000 && q.id);
  safeLocalStorage("usedQuestions", 'set', fresh);
  return [...new Set(fresh.map(q => q.id))];
}

function saveUsedQuestionLocal(questionId) {
  if (!questionId) return;
  const data = safeLocalStorage("usedQuestions", 'get') || [];
  const now = Date.now();
  if (!data.some(q => q.id === questionId && now - q.time < 3600000)) {
    data.push({ id: questionId, time: now });
    safeLocalStorage("usedQuestions", 'set', data);
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

function checkQuizAttemptLocal(ip) {
  const data = safeLocalStorage('quizAttempts', 'get') || {};
  const now = Date.now();
  const attempts = data[ip] ? data[ip].attempts || [] : [];
  const freshAttempts = attempts.filter(t => now - t < 3600000);
  return {
    allowed: freshAttempts.length < 3, // Allow up to 3 attempts per hour
    lastAttempt: attempts.length > 0 ? Math.max(...attempts) : null,
  };
}

function saveQuizAttemptLocal(ip) {
  const data = safeLocalStorage('quizAttempts', 'get') || {};
  const now = Date.now();
  if (!data[ip]) data[ip] = { attempts: [] };
  data[ip].attempts = (data[ip].attempts || []).filter(t => now - t < 3600000);
  data[ip].attempts.push(now);
  Object.keys(data).forEach(key => {
    if (!data[key].attempts || data[key].attempts.every(t => now - t >= 3600000)) {
      delete data[key];
    }
  });
  safeLocalStorage('quizAttempts', 'set', data);
}

// Periodic cleanup of localStorage
setInterval(() => {
  const now = Date.now();
  const usedQuestions = safeLocalStorage('usedQuestions', 'get') || [];
  const freshQuestions = usedQuestions.filter(q => now - q.time < 3600000 && q.id);
  safeLocalStorage('usedQuestions', 'set', freshQuestions);

  const quizAttempts = safeLocalStorage('quizAttempts', 'get') || {};
  Object.keys(quizAttempts).forEach(key => {
    quizAttempts[key].attempts = (quizAttempts[key].attempts || []).filter(t => now - t < 3600000);
    if (!quizAttempts[key].attempts || quizAttempts[key].attempts.length === 0) {
      delete quizAttempts[key];
    }
  });
  safeLocalStorage('quizAttempts', 'set', quizAttempts);
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
  const threshold = 150; // Increased to reduce false positives on mobile
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
  devPollId = setInterval(detectDevTools, 1000); // Slower poll to reduce load
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
    if (absenceTime > 60000 && !hiddenTabWarned) { // Increased to 60s
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
  if (inactiveTime > 240000 && !inactivityWarned) { // Increased to 4 minutes
    inactivityWarned = true;
    alert('Ntabwo uri mu ikizamini? Ikizamini kirangira mu munota 1!');
    setTimeout(() => {
      if (Date.now() - lastActivity > 300000) { // 5 minutes total
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

    if (questionsPool.length === 0) {
      throw new Error('No valid questions found in questions.json');
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
async function getExamSet(allQuestions, count = 20, maxImages = 3) {
  const used = getUsedQuestionsLocal();
  let available = allQuestions.filter(q => !used.includes(q.id));

  if (available.length < count) {
    alert(`Nta bibazo bihagije bishya bihari (${available.length}/${count}). Gerageza nyuma y'amasaha 1.`);
    return [];
  }

  const imageQuestions = available.filter(q => q.image);
  const noImageQuestions = available.filter(q => !q.image);

  let selected = [];

  let numImages = 0;
  if (imageQuestions.length > 0) {
    const maxPossible = Math.min(maxImages, imageQuestions.length, count);
    numImages = Math.floor(Math.random() * (maxPossible + 1));
    if (numImages > 0) {
      const shuffledImages = shuffleArray(imageQuestions);
      selected = selected.concat(shuffledImages.slice(0, numImages));
    }
  }

  let remainingCount = count - selected.length;
  const shuffledNoImages = shuffleArray(noImageQuestions);
  selected = selected.concat(shuffledNoImages.slice(0, remainingCount));

  if (selected.length < count) {
    const needed = count - selected.length;
    const remainingNoImages = shuffledNoImages.slice(remainingCount);
    let extraNoImages = remainingNoImages.slice(0, needed);
    selected = selected.concat(extraNoImages);
    let stillNeeded = needed - extraNoImages.length;

    if (stillNeeded > 0 && numImages < maxImages) {
      const additionalImages = Math.min(stillNeeded, maxImages - numImages);
      const remainingImages = imageQuestions.filter(img => !selected.some(s => s.id === img.id));
      const shuffledRemainingImages = shuffleArray(remainingImages);
      const extraImages = shuffledRemainingImages.slice(0, additionalImages);
      selected = selected.concat(extraImages);
      stillNeeded -= extraImages.length;
    }

    if (stillNeeded > 0) {
      const allExtra = available.filter(q => !selected.some(s => s.id === q.id));
      const shuffledExtra = shuffleArray(allExtra);
      const finalExtra = shuffledExtra.slice(0, stillNeeded);
      selected = selected.concat(finalExtra);
    }
  }

  if (selected.length !== count) {
    console.error(`Failed to select ${count} questions, got ${selected.length}`);
    alert(`Ikosa ryabaye mu guhitamo ibibazo ${count}. Gerageza vuba.`);
    return [];
  }

  const finalShuffled = shuffleArray(selected);

  finalShuffled.forEach(q => {
    const options = ['a', 'b', 'c', 'd'];
    const shuffledOptions = shuffleArray([...options]);
    const originalAnswerIdx = answerMap[q.answer];
    const newAnswerKey = shuffledOptions[originalAnswerIdx];
    q.shuffledOptions = shuffledOptions;
    q.shuffledAnswer = newAnswerKey;
  });

  finalShuffled.forEach(q => saveUsedQuestionLocal(q.id));

  return finalShuffled;
}

// =================== TIMER ===================
function startTimer() {
  const safeTimerEl = safeElementAccess(timerEl);
  if (!safeTimerEl) return;

  const savedEnd = safeLocalStorage("quizEndTime", 'get');
  if (savedEnd) {
    quizEndTime = new Date(savedEnd).getTime();
    const diff = Math.floor((quizEndTime - Date.now()) / 1000);
    timeLeft = Math.max(diff, 0);
  } else {
    quizEndTime = Date.now() + timeLeft * 1000;
    safeLocalStorage("quizEndTime", 'set', new Date(quizEndTime).toISOString());
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
      alert("Hasigaye umunota umwe ngo ikizamini kirangire!");
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

  safeImageContainer.innerHTML = q.image ? `<img src="${q.image}" class="question-img" alt="Question image" loading="lazy" onerror="this.style.display='none'">` : "";

  const shuffledKeys = q.shuffledOptions || ['a', 'b', 'c', 'd'];
  const inputs = safeOptionsForm.querySelectorAll("input[type='radio']");
  if (inputs.length !== 4) {
    console.error('Expected 4 radio inputs, found:', inputs.length);
    alert('Ikosa ryabaye mu kugaragaza ibyavugwa. Ikizamini ryahagaritswe.');
    endQuiz();
    return;
  }

  inputs.forEach((input, idx) => {
    const key = shuffledKeys[idx];
    const optionText = document.getElementById(`option-${key}`);
    const safeOptionText = safeElementAccess(optionText, { textContent: '' });
    safeOptionText.textContent = q[key] || '';
    input.checked = selectedAnswers[currentIndex] === idx;
    safeOptionText.style.backgroundColor = selectedAnswers[currentIndex] === idx ? "#e0e0e0" : "";
  });

  safePrevBtn.disabled = currentIndex === 0;
  safeNextBtn.disabled = false;

  if (currentIndex === questions.length - 1) {
    safeNextBtn.classList.add("hidden");
    safeSubmitBtn.classList.remove("hidden");
  } else {
    safeNextBtn.classList.remove("hidden");
    safeSubmitBtn.classList.add("hidden");
  }

  updateProgress();
}

// =================== SELECT OPTION ===================
function setupOptionListeners() {
  const safeOptionsForm = safeElementAccess(optionsForm);
  if (!safeOptionsForm) return;

  const inputs = safeOptionsForm.querySelectorAll("input[type='radio']");
  inputs.forEach((input, idx) => {
    input.addEventListener("change", () => {
      if (currentIndex >= 0 && currentIndex < selectedAnswers.length) {
        selectedAnswers[currentIndex] = idx;
        const labels = safeOptionsForm.querySelectorAll("label");
        labels.forEach((label, i) => {
          label.style.backgroundColor = i === idx ? "#e0e0e0" : "";
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
  safeLocalStorage("quizEndTime", 'set', null);
  const safeQuizScreen = safeElementAccess(quizScreen);
  const safeResultScreen = safeElementAccess(resultScreen);
  safeQuizScreen.classList.add('hidden');
  safeResultScreen.classList.remove('hidden');

  let score = 0;
  if (selectedAnswers.length === questions.length) {
    score = selectedAnswers.reduce((acc, ans, idx) => {
      const q = questions[idx];
      if (!q || !q.shuffledAnswer) return acc;
      const correctIdx = q.shuffledOptions.indexOf(q.shuffledAnswer);
      return acc + (ans === correctIdx ? 1 : 0);
    }, 0);
  }

  const safeScoreEl = safeElementAccess(scoreEl);
  safeScoreEl.textContent = `${score}/${questions.length}`;
  const safePassMessage = safeElementAccess(passMessage);
  const passed = score >= questions.length * 0.6;
  safePassMessage.textContent = passed ? "Watsinze 🎉" : "Watsinzwe ❌";
  safePassMessage.style.color = passed ? "green" : "red";

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
  safeReviewContainer.innerHTML = "";

  const fragment = document.createDocumentFragment();

  questions.forEach((q, idx) => {
    if (!q) return;
    const block = document.createElement("div");
    block.className = "question-block";

    block.innerHTML = `<h3>${idx + 1}. ${q.question}</h3>` +
      (q.image ? `<img src="${q.image}" class="question-img" alt="Review image" loading="lazy" onerror="this.style.display='none'">` : '');

    const shuffledKeys = q.shuffledOptions || ['a', 'b', 'c', 'd'];
    shuffledKeys.forEach((key, i) => {
      if (!q[key]) return;
      const correctIdx = shuffledKeys.indexOf(q.shuffledAnswer);
      let style = "";
      let mark = "";

      if (i === correctIdx) style += "color:green;font-weight:bold;";
      if (i === selectedAnswers[idx] && i !== correctIdx) style += "color:red;";

      if (i === selectedAnswers[idx]) {
        mark = i === correctIdx ? " ✅" : " ❌";
      } else if (i === correctIdx && selectedAnswers[idx] !== correctIdx) {
        mark = " ✅";
      }

      block.innerHTML += `<p style="${style}">${key.toUpperCase()}. ${q[key]}${mark}</p>`;
    });

    fragment.appendChild(block);
  });

  safeReviewContainer.appendChild(fragment);
}

// =================== START QUIZ ===================
async function startQuiz() {
  const pwd = prompt('Shyiramo ijambo ry’ibanga kugirango utangire ikizamini:');
  if (pwd !== authorizedPassword) {
    alert('Ntushobora gutangira ikizamini. Ijambo ry’ibanga ntiririho!');
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

  const canStart = checkQuizAttemptLocal(currentIP);
  if (!canStart.allowed) {
    const minutesLeft = Math.ceil((3600000 - (Date.now() - canStart.lastAttempt)) / 60000);
    alert(`Tegereza iminota ${minutesLeft} mbere yo kongera kugerageza ikizamini.`);
    return;
  }

  questions = await getExamSet(questionsPool, 20, 3);
  if (!questions || questions.length === 0) {
    return; // Alert already shown in getExamSet
  }

  saveQuizAttemptLocal(currentIP);

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