import sqlite3
import datetime
import random
from PIL import Image, ImageDraw, ImageFont
import os
import json
import sys
import google.generativeai as genai
api_key = open("api.key").read().strip()
genai.configure(api_key=api_key)
generation_config = {"temperature": 0.9, "top_p": 1, "top_k": 1, "max_output_tokens": 9998}
safety_settings = [
    {"category": "HARM_CATEGORY_HARASSMENT", "threshold": "BLOCK_NONE"},
    {"category": "HARM_CATEGORY_HATE_SPEECH", "threshold": "BLOCK_NONE"},
    {"category": "HARM_CATEGORY_SEXUALLY_EXPLICIT", "threshold": "BLOCK_NONE"},
    {"category": "HARM_CATEGORY_DANGEROUS_CONTENT", "threshold": "BLOCK_NONE"},
]
model = genai.GenerativeModel(model_name="gemini-2.5-flash-lite", generation_config=generation_config, safety_settings=safety_settings)
DB_FILE = "langle_game.db"
LANGUAGE_LIST = [
    {"language": "Spanish", "family": "Romance", "continent": "Europe"},
    {"language": "Hindi", "family": "Indo-European", "continent": "Asia"},
    {"language": "Portuguese", "family": "Romance", "continent": "Europe"},
    {"language": "Russian", "family": "Indo-European", "continent": "Europe"},
    {"language": "German", "family": "Germanic", "continent": "Europe"},
    {"language": "Turkish", "family": "Turkic", "continent": "Asia"},
    {"language": "French", "family": "Romance", "continent": "Europe"},
    {"language": "Vietnamese", "family": "Austroasiatic", "continent": "Asia"},
    {"language": "Italian", "family": "Romance", "continent": "Europe"},
    {"language": "Polish", "family": "West Slavic", "continent": "Europe"},
    {"language": "Serbian", "family": "South Slavic", "continent": "Europe"},
    {"language": "Ukrainian", "family": "East Slavic", "continent": "Europe"},
    {"language": "Czech", "family": "West Slavic", "continent": "Europe"},
    {"language": "Swedish", "family": "Norse", "continent": "Europe"},
    {"language": "Danish", "family": "Norse", "continent": "Europe"},
    {"language": "Norwegian", "family": "Norse", "continent": "Europe"},
    {"language": "Finnish", "family": "Uralic", "continent": "Europe"},
    {"language": "Hungarian", "family": "Uralic", "continent": "Europe"},
    {"language": "Romanian", "family": "Romance", "continent": "Europe"},
    {"language": "Dutch", "family": "Germanic", "continent": "Europe"},
    {"language": "Greek", "family": "Hellenic", "continent": "Europe"},
    {"language": "Bulgarian", "family": "South Slavic", "continent": "Europe"},
    {"language": "Slovak", "family": "West Slavic", "continent": "Europe"},
    {"language": "Lithuanian", "family": "Baltic", "continent": "Europe"},
    {"language": "Latvian", "family": "Baltic", "continent": "Europe"},
    {"language": "Estonian", "family": "Uralic", "continent": "Europe"},
    {"language": "Albanian", "family": "Indo-European", "continent": "Europe"},
    {"language": "Croatian", "family": "South Slavic", "continent": "Europe"},
    {"language": "Slovenian", "family": "South Slavic", "continent": "Europe"},
    {"language": "Bosnian", "family": "South Slavic", "continent": "Europe"},
    {"language": "Macedonian", "family": "South Slavic", "continent": "Europe"},
    {"language": "Montenegrin", "family": "South Slavic", "continent": "Europe"},
    {"language": "Icelandic", "family": "Norse", "continent": "Europe"},
    {"language": "Maltese", "family": "Semitic", "continent": "Europe"},
    {"language": "Irish", "family": "Celtic", "continent": "Europe"},
    {"language": "Welsh", "family": "Celtic", "continent": "Europe"},
    {"language": "Luxembourgish", "family": "Germanic", "continent": "Europe"},
    {"language": "Belarussian", "family": "East Slavic", "continent": "Europe"},
    {"language": "Mongolian", "family": "Mongolic", "continent": "Asia"},
    ]
DAYS_TO_AVOID = 10

def initialize_database():
    conn = sqlite3.connect(DB_FILE)
    cursor = conn.cursor()
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS daily_phrase (
            date TEXT PRIMARY KEY,
            language TEXT,
            family TEXT,
            continent TEXT,
            phrase TEXT,
            image_file TEXT,
            translation TEXT,
            country TEXT,
            spoken_by INTEGER,
            fun_fact TEXT
        )
    """)
    conn.commit()
    conn.close()

def get_recent_languages():
    conn = sqlite3.connect(DB_FILE)
    cursor = conn.cursor()
    cutoff_date = (datetime.date.today() - datetime.timedelta(days=DAYS_TO_AVOID)).isoformat()
    cursor.execute("""
        SELECT language FROM daily_phrase
        WHERE date >= ?
    """, (cutoff_date,))
    recent_languages = [row[0] for row in cursor.fetchall()]
    conn.close()
    return recent_languages

def select_language(recent_languages):
    return random.choice([lang for lang in LANGUAGE_LIST if lang["language"] not in recent_languages])

def generate_phrase(language):
    prompt = """The following references the """ + str(language['language']) + """ language. I would like you to generate information such that the phrase is no more than 20 characters but no less than 10 characters, and the phrase is a common every-day use phrase that makes it obvious which language it is; In addition, the phrase must be at least 2 words, AND the returned phrase must be in the native script of the language. You must ONLY return the information according to the following JSON:
    {
   "phrase": "String (original phrase in the target language)",
   "Translated Meaning": "String (translation of the phrase in English, WITH FULL AND CORRECT PUNCTUATION LIKE 'Hello World'!)",
   "Predominant Country": "String (the country where the language is most commonly spoken / or its country of origin (i.e England for English))",
   "Spoken By": "String (the number of people who speak the language worldwide, formatted like 300,000 or 1.5 million etc.)",
   "Fun Fact": "String (an interesting fact about the language or phrase)"   }
    """
    response = model.generate_content(prompt).text.strip()
    response = response[7:-3]
    print(response)
    response = json.loads(response)
    return response

def save_phrase_to_db(data):
    conn = sqlite3.connect(DB_FILE)
    cursor = conn.cursor()
    cursor.execute("""
        INSERT INTO daily_phrase (date, language, family, continent, phrase, image_file, translation, country, spoken_by, fun_fact)
        VALUES (:date, :language, :family, :continent, :phrase, :image_file, :translation, :country, :spoken_by, :fun_fact)
    """, data)
    conn.commit()
    conn.close()

def generate_image(phrase, font_path="arial.ttf", output_folder="images", filename=None):
    os.makedirs(output_folder, exist_ok=True)
    width, height = 600, 200
    background_color = "white"
    text_color = "black"
    image = Image.new("RGB", (width, height), color=background_color)
    draw = ImageDraw.Draw(image)
    try:
        font = ImageFont.truetype(font_path, 40)
    except IOError:
        raise ValueError(f"Font file not found at {font_path}. Please provide a valid font.")
    text_bbox = font.getbbox(phrase) 
    text_width = text_bbox[2] - text_bbox[0]
    text_height = text_bbox[3] - text_bbox[1] 
    text_x = (width - text_width) // 2
    text_y = (height - text_height) // 2
    draw.text((text_x, text_y), phrase, fill=text_color, font=font)
    filepath = os.path.join(output_folder, filename)
    image.save(filepath)
    return filepath

def select_language_by_name(language_name):
    # only used for testing [manually forcing a language]
    for language in LANGUAGE_LIST:
        if language["language"] == language_name:
            return language
    raise ValueError(f"Language {language_name} not found in the list of available languages.")

def main(forced_language=None):
    initialize_database()
    recent_languages = get_recent_languages()
    language = select_language(recent_languages)
    if forced_language:
        language = select_language_by_name(forced_language)
    try:
        phrase_json = generate_phrase(language)
    except Exception as e:
        try:
            phrase_json = generate_phrase(language)
        except Exception as e:
            phrase_json = generate_phrase(language)
    phrase = phrase_json["phrase"]
    image_file = generate_image(phrase, filename=f"{datetime.date.today().isoformat()}.png") # filename for generated image
    translation = phrase_json["Translated Meaning"]
    translation = translation[0].upper() + translation[1:]
    country = phrase_json["Predominant Country"]
    spoken_by = phrase_json["Spoken By"]
    fun_fact = phrase_json["Fun Fact"]
    data = {
        "date": str(datetime.date.today().isoformat()),
        "language": language["language"],
        "family": language["family"],
        "continent": language["continent"],
        "phrase": phrase,
        "image_file": image_file,
        "translation": translation,
        "country": country,
        "spoken_by": spoken_by,
        "fun_fact": fun_fact,
    }
    save_phrase_to_db(data)
    print(data)
if __name__ == "__main__":
    try:
        # for testing purposes, forcing a language by a command line argument
        forced_language = sys.argv[1]
    except:
        forced_language = None
    main(forced_language=forced_language)
