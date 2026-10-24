<?php

$GEMINI_API_KEY = getenv("GEMINI_API_KEY");

if (!$GEMINI_API_KEY) {
    die("Gemini API key is missing. Please configure the API key.");
}