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

// =================== SECURITY ===================
// Disable right-click and F12 / inspect
document.addEventListener('contextmenu', e => e.preventDefault());
document.addEventListener('keydown', e => {
  if (e.key === 'F12' || (e.ctrlKey && e.shiftKey && e.key === 'I')) e.preventDefault();
});

// =================== LOAD QUESTIONS ===================
async function loadQuestions() {
  try {
    const res = await fetch('questions.json');
    if (!res.ok) throw new Error(`Failed to load questions.json: ${res.statusText}`);
    const externalQuestions = await res.json();
    if (!Array.isArray(externalQuestions) || externalQuestions.length === 0) {
      throw new Error('questions.json is empty or not an array');
    }

    // Map questions and keep their original id field (important for usedQuestions)
    questionsPool = externalQuestions.map(q => ({
      id: q.id, // keep id
      question: q.question,
      image: q.image ? `images/${q.image}` : null,
      a: q.a,
      b: q.b,
      c: q.c,
      d: q.d,
      answer: (q.answer || '').toLowerCase()
    }));

    startBtn.disabled = false;
    console.log(`Loaded ${questionsPool.length} questions.`);
  } catch (err) {
    console.error('Failed to load questions:', err);
    alert('Error: Questions could not be loaded. Check console.');
  }
}
loadQuestions();

// =================== QUESTION HISTORY (60 min cache) ===================
// Stores used question IDs with timestamp in localStorage under "usedQuestions"
// Format: [{ id: 287, time: 169... }, ...]
function getUsedQuestions() {
  const data = JSON.parse(localStorage.getItem("usedQuestions")) || [];
  const now = Date.now();

  // Keep only those used within last 60 minutes (3600000 ms)
  const fresh = data.filter(q => now - q.time < 3600000);

  // Update storage to remove expired entries
  localStorage.setItem("usedQuestions", JSON.stringify(fresh));

  return fresh.map(q => q.id);
}

function saveUsedQuestion(questionId) {
  const data = JSON.parse(localStorage.getItem("usedQuestions")) || [];
  data.push({ id: questionId, time: Date.now() });
  localStorage.setItem("usedQuestions", JSON.stringify(data));
}

// Build an exam set of `count` questions avoiding ones used in last 60 minutes on this device
function getExamSet(allQuestions, count = 20) {
  const used = getUsedQuestions();
  const available = allQuestions.filter(q => !used.includes(q.id));

  if (available.length < count) {
    // Not enough fresh questions to guarantee uniqueness
    alert("Nta bibazo bihagije bishya bihari kuri iyi device. Gerageza nyuma y'amasaha 1.");
    return [];
  }

  const shuffled = shuffleArray([...available]).slice(0, count);

  // Save each selected question as used now
  shuffled.forEach(q => saveUsedQuestion(q.id));

  return shuffled;
}

// =================== TIMER ===================
function startTimer() {
  if (!timerEl) return;

  // check if we already have end time
  const savedEnd = localStorage.getItem("quizEndTime");
  if (savedEnd) {
    const diff = Math.floor((new Date(savedEnd).getTime() - Date.now()) / 1000);
    timeLeft = diff > 0 ? diff : 0;
  } else {
    const endTime = new Date(Date.now() + timeLeft * 1000);
    localStorage.setItem("quizEndTime", endTime.toISOString());
  }

  updateTimerDisplay();
  timerId = setInterval(() => {
    if (timeLeft <= 0) {
      clearInterval(timerId);
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
  if (!timerEl) return;
  const minutes = Math.floor(timeLeft / 60);
  const seconds = timeLeft % 60;
  timerEl.textContent = `Igihe: ${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
}

// =================== SHUFFLE ===================
function shuffleArray(array) {
  const arr = [...array];
  for (let i = arr.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [arr[i], arr[j]] = [arr[j], arr[i]];
  }
  return arr;
}

// =================== PROGRESS ===================
function updateProgress() {
  if (!progressBar || questions.length === 0) return;
  const prog = ((currentIndex + 1) / questions.length) * 100;
  progressBar.style.width = `${prog}%`;
  progressBar.textContent = `${currentIndex + 1}/${questions.length}`;
}

// =================== SHOW QUESTION ===================
function showQuestion() {
  if (questions.length === 0 || currentIndex >= questions.length) return;
  const q = questions[currentIndex];

  if (questionText) questionText.textContent = `${currentIndex + 1}. ${q.question}`;

  if (imageContainer) {
    imageContainer.innerHTML = q.image ? `<img src="${q.image}" class="question-img" alt="Question image" loading="lazy">` : "";
  }

  const keys = ['a','b','c','d'];
  if (optionsForm) {
    // assumes optionsForm has 4 input elements in order and labels with ids option-a..option-d
    const inputs = optionsForm.querySelectorAll("input");
    inputs.forEach((input, idx) => {
      const optionText = document.getElementById(`option-${keys[idx]}`);
      if (optionText) {
        optionText.textContent = q[keys[idx]];
        input.checked = selectedAnswers[currentIndex] === idx;
        optionText.style.backgroundColor = selectedAnswers[currentIndex] === idx ? "#e0e0e0" : "";
      }
    });
  }

  if (prevBtn) prevBtn.disabled = currentIndex === 0;
  if (nextBtn) nextBtn.disabled = false;

  if (currentIndex === questions.length - 1) {
    nextBtn.classList.add("hidden");
    submitBtn.classList.remove("hidden");
  } else {
    nextBtn.classList.remove("hidden");
    submitBtn.classList.add("hidden");
  }

  updateProgress();
}

// =================== SELECT OPTION ===================
if (optionsForm) {
  optionsForm.querySelectorAll("input").forEach((input, idx) => {
    input.addEventListener("change", () => {
      selectedAnswers[currentIndex] = idx;
      optionsForm.querySelectorAll("label").forEach((label, i) => {
        label.style.backgroundColor = i === idx ? "#e0e0e0" : "";
      });
    });
  });
}

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
  clearInterval(timerId);
  localStorage.removeItem("quizEndTime"); // clear timer persistence
  if (quizScreen && resultScreen) {
    quizScreen.classList.add('hidden');
    resultScreen.classList.remove('hidden');
  }

  const score = selectedAnswers.reduce((acc, ans, idx) => {
    const correctIdx = answerMap[questions[idx].answer];
    return acc + (ans === correctIdx ? 1 : 0);
  }, 0);

  if (scoreEl) scoreEl.textContent = `${score}/${questions.length}`;
  if (passMessage) {
    passMessage.textContent = score >= questions.length * 0.6 ? "Watsinze 🎉" : "Watsinzwe ❌";
    passMessage.style.color = score >= questions.length * 0.6 ? "green" : "red";
  }

  // Block going back
  window.onpopstate = e => { if (resultScreen && !resultScreen.classList.contains('hidden')) history.go(1); };
}

// =================== REVIEW ===================
function reviewAnswers() {
  if (resultScreen && reviewScreen) {
    resultScreen.classList.add('hidden');
    reviewScreen.classList.remove('hidden');
  }
  if (reviewContainer) reviewContainer.innerHTML = "";

  const fragment = document.createDocumentFragment();
  
  questions.forEach((q, idx) => {
    const block = document.createElement("div");
    block.className = "question-block";
    
    block.innerHTML = `<h3>${idx + 1}. ${q.question}</h3>` + 
      (q.image ? `<img src="${q.image}" class="question-img" alt="Review image" loading="lazy">` : '');

    ['a','b','c','d'].forEach((key, i) => {
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

  reviewContainer.appendChild(fragment);
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

  // Use 60-minute-safe exam set instead of naive shuffle
  questions = getExamSet(questionsPool, 20);
  if (!questions || questions.length === 0) {
    // getExamSet already alerts user if not enough fresh questions
    return;
  }

  selectedAnswers = new Array(questions.length).fill(null);
  currentIndex = 0;
  timeLeft = 20 * 60;
  warnedLastMinute = false;

  if (homeScreen && quizScreen) {
    homeScreen.classList.add('hidden');
    quizScreen.classList.remove('hidden');
  }

  showQuestion();
  startTimer();
}

// =================== EVENT LISTENERS ===================
if (startBtn) startBtn.addEventListener('click', startQuiz);
if (nextBtn) nextBtn.addEventListener('click', nextQuestion);
if (prevBtn) prevBtn.addEventListener('click', prevQuestion);
if (submitBtn) submitBtn.addEventListener('click', endQuiz);
if (restartBtn) restartBtn.addEventListener('click', () => location.reload());
if (reviewBtn) reviewBtn.addEventListener('click', reviewAnswers);
