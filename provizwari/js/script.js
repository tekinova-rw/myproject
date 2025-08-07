// const TOTAL_QUESTIONS = 20;
// let selectedQuestions = [];
// let currentQuestionIndex = 0;
// let userAnswers = JSON.parse(localStorage.getItem('provizwari_answers')) || new Array(TOTAL_QUESTIONS).fill(null);
// const optionKeys = ["a", "b", "c", "d"];
// const timerEl = document.getElementById('timer');
// let timeLeft = parseInt(localStorage.getItem('provizwari_time_left')) || 20 * 60; // 20 min in seconds
// let timerInterval;

// // Fungura ibibazo, uhitemo 20 ku buryo butunganye (shuffle)
// function shuffleArray(array) {
//   for (let i = array.length -1; i > 0; i--) {
//     const j = Math.floor(Math.random() * (i +1));
//     [array[i], array[j]] = [array[j], array[i]];
//   }
// }

// function startExam() {
//   if (!Array.isArray(questions) || questions.length < TOTAL_QUESTIONS) {
//     alert("Questions not loaded or not enough questions.");
//     return;
//   }
//   if (!localStorage.getItem('provizwari_questions')) {
//     shuffleArray(questions);
//     selectedQuestions = questions.slice(0, TOTAL_QUESTIONS);
//     localStorage.setItem('provizwari_questions', JSON.stringify(selectedQuestions));
//   } else {
//     selectedQuestions = JSON.parse(localStorage.getItem('provizwari_questions'));
//   }
//   showQuestion(currentQuestionIndex);
//   startTimer();
// }

// // Erekana ikibazo cya none
// function showQuestion(index) {
//   const container = document.getElementById('examContainer');
//   const q = selectedQuestions[index];

//   let optionsHtml = optionKeys.map((key, i) => `
//     <input id="option${i}" type="radio" name="answer" value="${i}" ${userAnswers[index] === i ? "checked" : ""} aria-label="Option ${key.toUpperCase()}: ${q[key]}">
//     <label for="option${i}" data-letter="${key.toUpperCase()}">${q[key]}</label>
//   `).join("");

//   let imageHtml = q.image ? `<img src="${q.image}" alt="Ikimenyetso cya ${q.question}">` : "";

//   container.innerHTML = `
//     <div class="progress">Question ${index + 1} of ${TOTAL_QUESTIONS}</div>
//     <div class="question">
//       <h3>Q${index + 1}. ${q.question}</h3>
//       ${imageHtml}
//       ${optionsHtml}
//       <div class="buttons">
//         <button id="backBtn" ${index === 0 ? "disabled" : ""} aria-label="Previous question">Garuka</button>
//         <button id="nextBtn">${index === TOTAL_QUESTIONS - 1 ? 'Ohereza Ikizamini' : 'Ibindi'}</button>
//       </div>
//     </div>
//   `;

//   document.getElementById('backBtn').onclick = () => {
//     saveAnswer();
//     if(currentQuestionIndex > 0){
//       currentQuestionIndex--;
//       showQuestion(currentQuestionIndex);
//     }
//   };

//   document.getElementById('nextBtn').onclick = () => {
//     saveAnswer();
//     currentQuestionIndex++;
//     if (currentQuestionIndex < TOTAL_QUESTIONS) {
//       showQuestion(currentQuestionIndex);
//     } else {
//       submitExam();
//     }
//   };
// }

// // Bika igisubizo cya user
// function saveAnswer() {
//   const selectedOption = document.querySelector('input[name="answer"]:checked');
//   if (!selectedOption) return; 
//   userAnswers[currentQuestionIndex] = Number(selectedOption.value);
//   localStorage.setItem('provizwari_answers', JSON.stringify(userAnswers));
// }

// // Iyo ikizamini cyoherejwe
// function submitExam() {
//   clearInterval(timerInterval);
//   let score = 0;
//   for (let i = 0; i < TOTAL_QUESTIONS; i++) {
//     if (userAnswers[i] === optionKeys.indexOf(selectedQuestions[i].answer)) {
//       score++;
//     }
//   }
//   localStorage.setItem('provizwari_score', score);
//   // Uhereza ku rupapuro rwa results.html
//   window.location.href = "results.html";
// }

// // Timer
// function startTimer() {
//   updateTimerDisplay();
//   timerInterval = setInterval(() => {
//     timeLeft--;
//     localStorage.setItem('provizwari_time_left', timeLeft);
//     updateTimerDisplay();

//     if (timeLeft <= 0) {
//       clearInterval(timerInterval);
//       alert("Igihe cyawe kirarangiye! Ikizamini kiratangwa.");
//       submitExam();
//     }
//   }, 1000);
// }

// function updateTimerDisplay() {
//   const minutes = Math.floor(timeLeft / 60);
//   const seconds = timeLeft % 60;
//   timerEl.textContent = `${minutes}:${seconds.toString().padStart(2,'0')}`;
// }

// window.onload = () => {
//   startExam();
// };
