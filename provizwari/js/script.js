let currentQuestionIndex = 0;
let selectedAnswers = [];
let examQuestions = [];

window.onload = function () {
  // Check mode before starting
  if (typeof SYSTEM_MODE !== "undefined" && SYSTEM_MODE === "paid") {
    alert("Please pay first before taking the exam.");
    window.location.href = "pay.html";
    return;
  }

  // Shuffle & pick 20 questions
  examQuestions = questions.sort(() => 0.5 - Math.random()).slice(0, 20);
  sessionStorage.setItem("currentQuestions", JSON.stringify(examQuestions));
  displayQuestion();

  startTimer(); // from timer.js
};

function displayQuestion() {
  const q = examQuestions[currentQuestionIndex];
  const container = document.getElementById("questionContainer");
  container.innerHTML = "";

  const div = document.createElement("div");
  div.classList.add("question");

  let html = `<p><b>Question ${currentQuestionIndex + 1} of ${examQuestions.length}</b></p>`;
  html += `<p>${q.question}</p>`;
  if (q.image) {
    html += `<img src="${q.image}" width="200"><br>`;
  }

  q.options.forEach((opt) => {
    const checked = selectedAnswers[currentQuestionIndex] === opt ? "checked" : "";
    html += `
      <label>
        <input type="radio" name="option" value="${opt}" ${checked} onchange="saveAnswer('${opt}')"> ${opt}
      </label><br>`;
  });

  div.innerHTML = html;
  container.appendChild(div);
}

function saveAnswer(answer) {
  selectedAnswers[currentQuestionIndex] = answer;
}

function nextQuestion() {
  if (currentQuestionIndex < examQuestions.length - 1) {
    currentQuestionIndex++;
    displayQuestion();
  } else {
    submitExam(); // If on last question, submit
  }
}

function prevQuestion() {
  if (currentQuestionIndex > 0) {
    currentQuestionIndex--;
    displayQuestion();
  }
}

function submitExam() {
  sessionStorage.setItem("userAnswers", JSON.stringify(selectedAnswers));
  window.location.href = "result.html";
}
