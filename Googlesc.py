from flask import Flask, request, jsonify
import requests
import hashlib
import base64
import math

app = Flask(__name__)

API_KEY = "CUSTOM_SEARCH_API"
SEARCH_ENGINE_ID = "ENGINE_ID"

HEADERS = {
    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.3p6",
    "Accept": "application/json",
    "Connection": "keep-alive"
}

def encrypt_text(text):
    return base64.b64encode(text.encode()).decode() if text else "N/A"

def calculate_hash(text):
    return hashlib.sha256(text.encode()).hexdigest() if text else "N/A"

@app.route('/search', methods=['GET'])
def google_search():
    query = request.args.get('q')
    if not query:
        return jsonify({"error": "Query parameter 'q' is required"}), 400

    url = f"https://www.googleapis.com/customsearch/v1?q={query}&key={API_KEY}&cx={SEARCH_ENGINE_ID}"
    
    try:
        response = requests.get(url, headers=HEADERS, timeout=10)
        response.raise_for_status()
        data = response.json()
    except requests.exceptions.RequestException as e:
        return jsonify({"error": str(e)}), 500

    results = []
    for item in data.get("items", []):
        title = item.get("title", "No Title")
        description = item.get("snippet", "No Description")
        website_name = item.get("displayLink", "Unknown Website")
        full_url = item.get("link", "N/A")
        cache_link = f"https://webcache.googleusercontent.com/search?q=cache:{item.get('cacheId')}" if item.get("cacheId") else "N/A"
        published_time = item.get("pagemap", {}).get("metatags", [{}])[0].get("article:published_time", "N/A")
        thumbnail = item.get("pagemap", {}).get("cse_image", [{}])[0].get("src", "N/A")
        meta_description = item.get("pagemap", {}).get("metatags", [{}])[0].get("og:description", "N/A")
        meta_keywords = item.get("pagemap", {}).get("metatags", [{}])[0].get("keywords", "N/A")
        language = item.get("pagemap", {}).get("metatags", [{}])[0].get("og:locale", "N/A")
        page_category = item.get("pagemap", {}).get("metatags", [{}])[0].get("og:type", "N/A")
        
        title_length = len(title)
        description_length = len(description)
        title_sqrt = round(math.sqrt(title_length), 2) if title_length > 0 else 0
        desc_sqrt = round(math.sqrt(description_length), 2) if description_length > 0 else 0

        encrypted_url = encrypt_text(full_url)
        encrypted_cache = encrypt_text(cache_link)
        url_hash = calculate_hash(full_url)
        title_hash = calculate_hash(title)

        results.append({
            "Title": title,
            "Description": description,
            "Website Name": website_name,
            "Full URL": full_url,
            "Published Time": published_time,
            "Cache Link": cache_link,
            "Thumbnail": thumbnail,
            "Meta Description": meta_description,
            "Meta Keywords": meta_keywords,
            "Language": language,
            "Page Category": page_category,
            "Title Length": title_length,
            "Description Length": description_length,
            "Title Sqrt": title_sqrt,
            "Description Sqrt": desc_sqrt,
            "Encrypted URL": encrypted_url,
            "Encrypted Cache": encrypted_cache,
            "URL Hash": url_hash,
            "Title Hash": title_hash
        })

    return jsonify({"Starexx": results})

if __name__ == '__main__':
    app.run(debug=True)
