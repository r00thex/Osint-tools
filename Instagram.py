from flask import Flask, request, jsonify
import requests

app = Flask(__name__)

IG_APP_ID = "936619743392459"
BASE_URL = "https://i.instagram.com/api/v1/users/web_profile_info/"


@app.route("/info", methods=["GET"])
def info():
    username = request.args.get("username", "").strip()

    # username নেওয়া
    if not username:
        return jsonify({
            "error": "missing_username",
            "use": "/info?username=<name>"
        }), 400

    url = f"{BASE_URL}?username={username}"

    headers = {
        "User-Agent": "Mozilla/5.0",
        "x-ig-app-id": IG_APP_ID,
        "Accept": "application/json",
        "Referer": f"https://www.instagram.com/{username}/",
    }

    try:
        resp = requests.get(url, headers=headers, timeout=15)
    except requests.RequestException as e:
        return jsonify({"error": "request_failed", "detail": str(e)}), 500

    # error handle
    if resp.status_code == 404:
        return jsonify({"error": "not_found"}), 404

    if resp.status_code != 200:
        return jsonify({
            "error": "http_error",
            "code": resp.status_code
        }), 400

    # JSON decode
    try:
        data = resp.json()
    except ValueError:
        return jsonify({"error": "invalid_json"}), 500

    user = (data.get("data") or {}).get("user")
    if not user:
        return jsonify({"raw": data})

    # recent posts
    media = (
        (user.get("edge_owner_to_timeline_media") or {}).get("edges") or []
    )
    recent = []
    for item in media[:8]:
        node = item.get("node", item)

        caption_edges = (
            (node.get("edge_media_to_caption") or {}).get("edges") or []
        )
        cap = None
        if caption_edges:
            cap = (caption_edges[0].get("node") or {}).get("text")

        recent.append({
            "id": node.get("id"),
            "code": node.get("shortcode"),
            "img": node.get("display_url"),
            "cap": cap,
        })

    # final response
    output = {
        "id": user.get("id"),
        "username": user.get("username"),
        "name": user.get("full_name"),
        "bio": user.get("biography"),
        "verified": user.get("is_verified"),
        "private": user.get("is_private"),
        "pic": user.get("profile_pic_url_hd") or user.get("profile_pic_url"),
        "followers": (user.get("edge_followed_by") or {}).get("count", 0),
        "following": (user.get("edge_follow") or {}).get("count", 0),
        "posts": (user.get("edge_owner_to_timeline_media") or {}).get("count", 0),
        "recent": recent,
    }

    return jsonify(output)


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=True)
