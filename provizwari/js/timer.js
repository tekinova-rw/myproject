// timer.js
let totalTime = 20 * 60; // 20 minutes in seconds

function startTimer() {
  const timerDisplay = document.getElementById("timer");

  const timer = setInterval(() => {
    let minutes = Math.floor(totalTime / 60);
    let seconds = totalTime % 60;

    // Format time
    const formattedTime = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    timerDisplay.textContent = `Time Left: ${formattedTime}`;

    if (totalTime <= 0) {
      clearInterval(timer);
      alert("Time is up!");
      submitExam(); // Automatically submit
    }

    totalTime--;
  }, 1000);
}

window.onload = function () {
  if (typeof buildExam === "function") {
    buildExam(); // optional wrapper for exam structure
  }
  startTimer(); // start countdown
};
