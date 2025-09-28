# Langle 🌎
**A testing instance is available at [peter.rs/projects/langle](https://peter.rs/projects/langle)!**
> Can you guess the language? A daily language guessing game inspired by Wordle.

Langle is a simple web-based puzzle game where a new phrase is presented each day. Your challenge is to correctly identify the language it's written in. Test your linguistic knowledge and pattern recognition skills every day!

![Langle Gameplay Screenshot](https://i.imgur.com/TlC6ZSQ.png)

---

## ✨ Features

* **Daily Puzzles:** A new language challenge is automatically generated every day.
* **Simple Interface:** Clean and intuitive gameplay, accessible from any web browser.
* **Hint System:** Get feedback on your guesses, such as language family or continent, to help you narrow down the possibilities.
* **Bonus Quizzes:** After a correct guess, test your knowledge further with questions about the language's meaning, origin, and number of speakers.
* **Social Features:** Easily share your results with friends and family with both long-form and short ('wordle'-like) summaries after the game!
---

## ⚙️ Getting Started

### Prerequisites

You will need a server environment with the following installed:

* A web server with PHP support (e.g., Apache, Nginx)
* Python 3.x
* `pip` 
* `crontab`, or any other way to routinely run the script.

### Installation

1.  **Clone the Repository**
    Clone this repository to your local machine or server. It's recommended to place it in your web server's public directory (e.g., `/var/www/html/`).
    ```sh
    git clone https://github.com/peter.rs/langle.git
    cd langle
    ```

2.  **Install Python Dependencies**
    The required Python packages are listed in `requirements.txt`. Install them using `pip`.
    ```sh
    pip install -r requirements.txt
    ```

3.  **Set Up the Daily Cron Job**
    A cron job is needed to run the `script.py` automatically every day at midnight. This script generates the puzzle for the new day.

    * Open your crontab editor:
        ```sh
        crontab -e
        ```
    * Add the following line to the file. This schedule will run the script every day at `00:00` (midnight).
        ```crontab
        0 0 * * * /usr/bin/python3 /path/to/your/project/script.py
        ```

---

## ▶️ Usage

Once the installation and cron job are set up, the game is ready to play!

Simply navigate to the `index.php` file in your web browser.