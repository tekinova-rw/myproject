let timerInterval;

function startTimer(duration) {
  let timer = duration, minutes, seconds;
  const timerElement = document.getElementById('timer');

  clearInterval(timerInterval);

  timerInterval = setInterval(() => {
    minutes = Math.floor(timer / 60);
    seconds = timer % 60;

    minutes = minutes < 10 ? "0" + minutes : minutes;
    seconds = seconds < 10 ? "0" + seconds : seconds;

    timerElement.textContent = `Time Left: ${minutes}:${seconds}`;

    if (--timer < 0) {
      clearInterval(timerInterval);
      alert("Time is up! Exam will be submitted automatically.");
      submitExam();
    }
  }, 1000);
}
