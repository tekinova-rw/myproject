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
const answerMap = { a: 0, b: 1, c: 2, d: 3 };
const authorizedPassword = ''; // Password to start quiz

// =================== UTILITY FUNCTIONS ===================
function safeLocalStorage(key, operation = 'get', value = null) {
  try {
    if (operation === 'set') {
      localStorage.setItem(key, JSON.stringify(value));
      return true;
    } else {
      const data = localStorage.getItem(key);
      return data ? JSON.parse(data) : null;
    }
  } catch (err) {
    console.error(`LocalStorage error for ${key}:`, err);
    // Fallback: clear or ignore as needed
    if (operation === 'set') return false;
    return null;
  }
}

function safeElementAccess(el, fallback = null) {
  return el || { textContent: '', innerHTML: '', classList: { add: () => {}, remove: () => {}, toggle: () => {} }, style: {}, disabled: false };
}

// =================== SECURITY ===================
// Disable right-click and F12 / inspect
document.addEventListener('contextmenu', e => e.preventDefault());
document.addEventListener('keydown', e => {
  if (e.key === 'F12' || (e.ctrlKey && e.shiftKey && e.key === 'I')) e.preventDefault();
});

// Enhanced security: Disable text selection, copy/paste/print, and screenshot keys
document.addEventListener('selectstart', e => e.preventDefault());
document.addEventListener('keydown', e => {
  const forbiddenKeys = [
    // Copy, cut, paste
    (e.ctrlKey || e.metaKey) && (e.key === 'c' || e.key === 'x' || e.key === 'v' || e.key === 'a'),
    // Print
    (e.ctrlKey || e.metaKey) && e.key === 'p',
    // Screenshot
    e.key === 'PrintScreen' || (e.ctrlKey && e.shiftKey && e.key === 'I') // Also block Ctrl+Shift+I again for emphasis
  ];
  if (forbiddenKeys.some(condition => condition)) {
    e.preventDefault();
    return false;
  }
});

// Disable drag and drop
document.addEventListener('dragstart', e => e.preventDefault());
document.addEventListener('drop', e => e.preventDefault());

// Prevent page caching to avoid back-button exploits
window.addEventListener('pageshow', e => {
  if (e.persisted) {
    window.location.reload();
  }
});

// Basic dev tools detection (poll every 500ms during quiz)
let devToolsDetected = false;
function detectDevTools() {
  if (!quizScreen || quizScreen.classList.contains('hidden')) return;
  const threshold = 100;
  if (window.outerHeight - window.innerHeight > threshold || window.outerWidth - window.innerWidth > threshold) {
    if (!devToolsDetected) {
      devToolsDetected = true;
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

// =================== LOAD QUESTIONS ===================
async function loadQuestions() {
  try {
    const res = await fetch('questions.json');
    if (!res.ok) throw new Error(`Failed to load questions.json: ${res.statusText}`);
    const externalQuestions = await res.json();
    if (!Array.isArray(externalQuestions) || externalQuestions.length === 0) {
      throw new Error('questions.json is empty or not an array');
    }

    // Validate and map questions
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
// Strictly enforce no repeats within 60 minutes - already implemented, but added stricter validation
function getUsedQuestions() {
  const data = safeLocalStorage("usedQuestions", 'get') || [];
  const now = Date.now();

  // Keep only those used within last 60 minutes (3600000 ms) - strict filter
  const fresh = data.filter(q => now - q.time < 3600000 && q.id); // Also filter invalid entries

  // Update storage to remove expired entries
  safeLocalStorage("usedQuestions", 'set', fresh);

  return [...new Set(fresh.map(q => q.id))]; // Dedupe IDs if somehow duplicated
}

function saveUsedQuestion(questionId) {
  if (!questionId) return; // Strict: ignore invalid IDs
  const data = safeLocalStorage("usedQuestions", 'get') || [];
  const now = Date.now();
  // Prevent duplicates in current session
  if (!data.some(q => q.id === questionId && now - q.time < 60000)) { // Within 1 min to avoid spam
    data.push({ id: questionId, time: now });
    safeLocalStorage("usedQuestions", 'set', data);
  }
}

// Build an exam set of `count` questions avoiding ones used in last 60 minutes on this device
// Now with max 3 image questions (not mandatory, random inclusion up to 3), and final shuffle for no order
function getExamSet(allQuestions, count = 20, maxImages = 3) {
  const used = getUsedQuestions();
  let available = allQuestions.filter(q => !used.includes(q.id));

  if (available.length < count) {
    alert("Nta bibazo bihagije bishya bihari kuri iyi device. Gerageza nyuma y'amasaha 1.");
    return [];
  }

  // Separate image and non-image questions
  const imageQuestions = available.filter(q => q.image);
  const noImageQuestions = available.filter(q => !q.image);

  let selected = [];

  // Optionally select up to maxImages from image questions (not mandatory, but can include randomly)
  let numImages = 0;
  if (imageQuestions.length > 0) {
    // Randomly decide how many images to include (0 to min(maxImages, available, count))
    const maxPossible = Math.min(maxImages, imageQuestions.length, count);
    numImages = Math.floor(Math.random() * (maxPossible + 1)); // 0 to maxPossible inclusive
    if (numImages > 0) {
      const shuffledImages = shuffleArray(imageQuestions);
      selected = selected.concat(shuffledImages.slice(0, numImages));
    }
  }

  // Select remaining from no-image questions (prioritize, but if short, can add more images if needed)
  let remainingCount = count - selected.length;
  const shuffledNoImages = shuffleArray(noImageQuestions);
  selected = selected.concat(shuffledNoImages.slice(0, remainingCount));

  // If still short (use remaining images or available, but respect maxImages)
  if (selected.length < count) {
    const needed = count - selected.length;
    // First, try remaining no-image if any
    const remainingNoImages = shuffledNoImages.slice(remainingCount);
    let extraNoImages = remainingNoImages.slice(0, needed);
    selected = selected.concat(extraNoImages);
    let stillNeeded = needed - extraNoImages.length;

    // If still needed, add from remaining images (but don't exceed maxImages)
    if (stillNeeded > 0 && numImages < maxImages) {
      const additionalImages = Math.min(stillNeeded, maxImages - numImages);
      const remainingImages = imageQuestions.filter(img => !selected.some(s => s.id === img.id));
      const shuffledRemainingImages = shuffleArray(remainingImages);
      const extraImages = shuffledRemainingImages.slice(0, additionalImages);
      selected = selected.concat(extraImages);
      numImages += extraImages.length;
      stillNeeded -= extraImages.length;
    }

    // Absolute fallback: any remaining available (rare, but ensures count)
    if (stillNeeded > 0) {
      const allExtra = available.filter(q => !selected.some(s => s.id === q.id));
      const shuffledExtra = shuffleArray(allExtra);
      const finalExtra = shuffledExtra.slice(0, stillNeeded);
      selected = selected.concat(finalExtra);
    }
  }

  // Final shuffle to ensure random order (no specific image ordering)
  const finalShuffled = shuffleArray(selected);

  // Save each selected question as used now - strict
  finalShuffled.forEach(q => saveUsedQuestion(q.id));

  return finalShuffled;
}

// =================== TIMER ===================
function startTimer() {
  const safeTimerEl = safeElementAccess(timerEl);
  if (!safeTimerEl) return;

  // check if we already have end time
  const savedEnd = safeLocalStorage("quizEndTime", 'get');
  if (savedEnd) {
    const diff = Math.floor((new Date(savedEnd).getTime() - Date.now()) / 1000);
    timeLeft = Math.max(diff, 0);
  } else {
    const endTime = new Date(Date.now() + timeLeft * 1000);
    safeLocalStorage("quizEndTime", 'set', endTime.toISOString());
  }

  updateTimerDisplay();
  timerId = setInterval(() => {
    if (timeLeft <= 0) {
      clearInterval(timerId);
      timerId = null;
      endQuiz();
      return;
    }

    if (timeLeft === 60 && !warnedLastMinute) {
      warnedLastMinute = true;
      alert("Hasigaye umunota umwe ngo ikizamini kirangire!");
    }

    timeLeft--;
    updateTimerDisplay();
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
    console.error('Invalid question index:', currentIndex);
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

  const keys = ['a', 'b', 'c', 'd'];
  const inputs = safeOptionsForm.querySelectorAll("input[type='radio']");
  if (inputs.length !== 4) {
    console.error('Expected 4 radio inputs in options form');
    return;
  }

  inputs.forEach((input, idx) => {
    const optionText = document.getElementById(`option-${keys[idx]}`);
    const safeOptionText = safeElementAccess(optionText, { textContent: '' });
    safeOptionText.textContent = q[keys[idx]] || '';
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
    });
  });
}
setupOptionListeners(); // Setup on load

// =================== NAVIGATION ===================
function nextQuestion() {
  if (currentIndex < questions.length - 1) {
    currentIndex++;
    showQuestion();
  } else {
    endQuiz();
  }
}

function prevQuestion() {
  if (currentIndex > 0) {
    currentIndex--;
    showQuestion();
  }
}

// =================== END QUIZ ===================
function endQuiz() {
  stopTimer();
  safeLocalStorage("quizEndTime", 'set', null); // clear timer persistence
  const safeQuizScreen = safeElementAccess(quizScreen);
  const safeResultScreen = safeElementAccess(resultScreen);
  safeQuizScreen.classList.add('hidden');
  safeResultScreen.classList.remove('hidden');

  let score = 0;
  if (selectedAnswers.length === questions.length) {
    score = selectedAnswers.reduce((acc, ans, idx) => {
      const q = questions[idx];
      if (!q || !q.answer) return acc;
      const correctIdx = answerMap[q.answer];
      return acc + (ans === correctIdx ? 1 : 0);
    }, 0);
  }

  const safeScoreEl = safeElementAccess(scoreEl);
  safeScoreEl.textContent = `${score}/${questions.length}`;
  const safePassMessage = safeElementAccess(passMessage);
  const passed = score >= questions.length * 0.6;
  safePassMessage.textContent = passed ? "Watsinze 🎉" : "Watsinzwe ❌";
  safePassMessage.style.color = passed ? "green" : "red";

  // Block going back
  window.onpopstate = e => { 
    if (!safeResultScreen.classList.contains('hidden')) {
      history.go(1); 
    }
  };
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

    ['a','b','c','d'].forEach((key, i) => {
      if (!q[key]) return;
      const correctIdx = answerMap[q.answer];
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

  // Use 60-minute-safe exam set with max 3 images (optional, random number up to 3)
  questions = getExamSet(questionsPool, 20, 3);
  if (!questions || questions.length === 0) {
    return;
  }

  selectedAnswers = new Array(questions.length).fill(null);
  currentIndex = 0;
  timeLeft = 20 * 60;
  warnedLastMinute = false;
  devToolsDetected = false;

  const safeHomeScreen = safeElementAccess(homeScreen);
  const safeQuizScreen = safeElementAccess(quizScreen);
  safeHomeScreen.classList.add('hidden');
  safeQuizScreen.classList.remove('hidden');

  showQuestion();
  startTimer();
  startDevPoll(); // Start dev tools detection
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

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
  stopTimer();
});