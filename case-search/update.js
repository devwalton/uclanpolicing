document.addEventListener("DOMContentLoaded", function() {
    fetch('https://uclanpolicing.vercel.app/update.php')
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.text();
    })
    .then(data => {
        // Log the result from update.php if needed
        console.log('Result from update.php:', data);

        // Fetch data from scrape.php
        return fetch('https://uclanpolicing.vercel.app/scrape.php');
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.text();
    })
    .then(data => {
        // Log the result from scrape.php if needed
        console.log('Result from scrape.php:', data);

        // Do something with the result, or just ignore it
    })
    .catch(error => console.error('Error fetching content:', error));
});
