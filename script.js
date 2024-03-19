const introTextElement = document.getElementById('intro-text');
        const introText = "Hello, I'm Tom, a policing student and full-stack developer in my free time.";
        let charIndex = 0;

        function typeIntroText() {
            if (charIndex < introText.length) {
                introTextElement.innerHTML += introText.charAt(charIndex);
                charIndex++;
                setTimeout(typeIntroText, 50);
            }
        }

        document.addEventListener("DOMContentLoaded", typeIntroText);