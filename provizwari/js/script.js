let currentQuestionIndex = 0;
let userAnswers = [];
let questionsToUse = [];

const questionContainer = document.getElementById("questionContainer");
const backBtn = document.getElementById("backBtn");
const nextBtn = document.getElementById("nextBtn");
const submitBtn = document.getElementById("submitBtn");

function getRandomQuestions(allQuestions, num) {
  const shuffled = [...allQuestions].sort(() => 0.5 - Math.random());
  return shuffled.slice(0, num);
}

function displayQuestion() {
  const q = questionsToUse[currentQuestionIndex];
  if (!q) return;

  let html = `<h3>Question ${currentQuestionIndex + 1} of ${questionsToUse.length}</h3>`;
  html += `<p>${q.question}</p>`;

  if (q.image) {
    html += `<img src="${q.image}" alt="Question Image" />`;
  }

  q.options.forEach(option => {
    const checked = userAnswers[currentQuestionIndex] === option ? "checked" : "";
    html += `<label><input type="radio" name="answer" value="${option}" ${checked}> ${option}</label>`;
  });

  questionContainer.innerHTML = html;

  backBtn.disabled = currentQuestionIndex === 0;
  nextBtn.style.display = currentQuestionIndex === questionsToUse.length - 1 ? "none" : "inline-block";
  submitBtn.style.display = currentQuestionIndex === questionsToUse.length - 1 ? "inline-block" : "none";
}

function saveAnswer() {
  const selectedOption = document.querySelector('input[name="answer"]:checked');
  if (selectedOption) {
    userAnswers[currentQuestionIndex] = selectedOption.value;
  }
}

function nextQuestion() {
  saveAnswer();
  if (currentQuestionIndex < questionsToUse.length - 1) {
    currentQuestionIndex++;
    displayQuestion();
  }
}

function prevQuestion() {
  saveAnswer();
  if (currentQuestionIndex > 0) {
    currentQuestionIndex--;
    displayQuestion();
  }
}

function submitExam() {
  saveAnswer();

  let score = 0;
  questionsToUse.forEach((q, i) => {
    if (userAnswers[i] === q.answer) score++;
  });

  alert(`You scored ${score} out of ${questionsToUse.length}`);

  // Ushobora gushyiramo redirect cyangwa kubika amanota muri localStorage/sessionStorage
  // window.location.href = "result.html";
}

window.onload = () => {
  questionsToUse = getRandomQuestions(questions, 20);
  userAnswers = new Array(questionsToUse.length).fill(null);
  displayQuestion();
  startTimer(20 * 60);
};
function submitExam() {
  saveAnswer();

  let score = 0;
  questionsToUse.forEach((q, i) => {
    if (userAnswers[i] === q.answer) score++;
  });

  // Bika amanota muri localStorage
  localStorage.setItem('provizwari_score', score);
  localStorage.setItem('provizwari_total', questionsToUse.length);

  // Redirect ujye kuri result page
  window.location.href = "result.html";
}
