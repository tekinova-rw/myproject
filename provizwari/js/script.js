let currentQuestionIndex = 0;
let selectedQuestions = [];
let userAnswers = new Array(20).fill(null);

function pickRandomQuestions() {
  if (!window.questions || !Array.isArray(window.questions)) {
    console.error('Questions not loaded from questions.js');
    return [];
  }
  const shuffled = [...window.questions].sort(() => 0.5 - Math.random());
  return shuffled.slice(0, 20);
}

function loadQuestion(index) {
  if (!selectedQuestions[index]) return;
  const question = selectedQuestions[index];
  document.getElementById('questionNumber').textContent = `Ikibazo ${index + 1} cya 20`;
  document.getElementById('questionText').textContent = question.question;
  const img = document.getElementById('questionImage');
  img.style.display = question.image ? 'block' : 'none';
  if (question.image) {
    img.src = question.image;
    img.alt = 'Icyapa cyo mu kibazo';
  }
  const form = document.getElementById('answersForm');
  form.querySelectorAll('label').forEach((label, i) => {
    const option = ['a', 'b', 'c', 'd'][i];
    label.classList.toggle('selected', userAnswers[index] === option);
    label.querySelector('input').checked = userAnswers[index] === option;
    label.querySelector('span').textContent = `${option.toUpperCase()}. ${question[option] || 'No option'}`;
  });
  document.getElementById('backBtn').disabled = index === 0;
  document.getElementById('nextBtn').disabled = !userAnswers[index];
  document.getElementById('submitBtn').style.display = index === 19 && userAnswers[index] ? 'inline-block' : 'none';
  document.getElementById('reviewBtn').style.display = 'none';
  document.getElementById('homeBtn').style.display = 'none';
}

function finishExam() {
  window.timer.stopTimer();
  const score = userAnswers.reduce((acc, answer, i) => acc + (answer === selectedQuestions[i].answer ? 1 : 0), 0);
  localStorage.setItem('lastSelectedQuestions', JSON.stringify(selectedQuestions));
  localStorage.setItem('lastUserAnswers', JSON.stringify(userAnswers));
  window.location.href = `results.html?score=${score}&total=20`;
}

function initExam() {
  if (window.mode.hasBonusAccess() || window.mode.getAccessMode() === 'free') {
    selectedQuestions = pickRandomQuestions();
    if (selectedQuestions.length === 0) {
      alert('No questions available. Please check questions.js.');
      return;
    }
    window.timer.startTimer(finishExam);
    loadQuestion(0);
  } else {
    window.mode.requireBonusAccess('pay.html');
  }
}

document.getElementById('answersForm').addEventListener('change', (e) => {
  const form = document.getElementById('answersForm');
  userAnswers[currentQuestionIndex] = e.target.value;
  form.querySelectorAll('label').forEach(label => {
    label.classList.toggle('selected', label.querySelector('input').value === e.target.value);
  });
  loadQuestion(currentQuestionIndex);
});

document.getElementById('backBtn').addEventListener('click', () => {
  if (currentQuestionIndex > 0) {
    currentQuestionIndex--;
    loadQuestion(currentQuestionIndex);
  }
});

document.getElementById('nextBtn').addEventListener('click', () => {
  if (currentQuestionIndex < 19 && userAnswers[currentQuestionIndex]) {
    currentQuestionIndex++;
    loadQuestion(currentQuestionIndex);
  }
});

document.getElementById('submitBtn').addEventListener('click', finishExam);

document.getElementById('homeBtn').addEventListener('click', () => {
  window.timer.stopTimer();
  window.location.href = 'index.html';
});

window.onload = initExam;