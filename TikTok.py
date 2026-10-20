from flask import Flask, request, jsonify
import requests
import html

app = Flask(__name__)
app.config["JSON_AS_ASCII"] = False  # unicode অক্ষুণ্ণ রাখতে


@app.route("/tiktokinfo", methods=["GET"])
def tiktok_info():
    username = request.args.get("username", "")

    if not username:
        return jsonify({
            "success": False,
            "message": "Username required"
        }), 400

    # Remove @
    username = username.replace("@", "").strip()

    url = f"https://www.tikwm.com/api/user/info?unique_id={username}"

    headers = {
        "User-Agent": "Mozilla/5.0"
    }

    try:
        resp = requests.get(url, headers=headers, timeout=30, allow_redirects=True)
    except requests.RequestException as e:
        return jsonify({
            "success": False,
            "message": str(e)
        }), 500

    try:
        data = resp.json()
    except ValueError:
        return jsonify({
            "success": False,
            "message": "API failed",
            "raw": resp.text
        }), 502

    if not data or "data" not in data:
        return jsonify({
            "success": False,
            "message": "API failed",
            "raw": resp.text
        }), 502

    d = data.get("data") or {}
    user = d.get("user") or {}
    stats = d.get("stats") or {}

    # Fallback fields
    unique_id = user.get("unique_id") or user.get("uniqueId") or username
    uid = user.get("uid") or user.get("id") or ""
    nickname = user.get("nickname") or "No name"
    bio = user.get("signature") or ""
    avatar = user.get("avatar") or user.get("avatarLarger") or ""
    avatar = html.unescape(avatar)

    verified = "Yes" if user.get("verified") else "No"
    private = "Yes" if user.get("private_account") else "No"
    region = user.get("region") or "No region found"
    sec_uid = user.get("sec_uid") or user.get("secUid") or ""

    result = {
        "success": True,
        "info": {
            "title": "📱 TikTok User Information 📱",
            "username": f"@{unique_id}",
            "user_id": uid,
            "name": nickname,
            "verified": verified,
            "private_account": private,
            "region": region,
            "followers": stats.get("followerCount", 0),
            "following": stats.get("followingCount", 0),
            "likes": stats.get("heart", 0),
            "videos": stats.get("videoCount", 0),
            "friends": stats.get("friendCount", 0),
            "heart": stats.get("heart", 0),
            "digg_count": stats.get("diggCount", 0),
            "sec_uid": sec_uid,
            "bio": bio,
            "profile_url": f"https://www.tiktok.com/@{unique_id}",
            "avatar": avatar,
        },
    }

    return app.response_class(
        response=__import__("json").dumps(
            result,
            ensure_ascii=False,
            indent=2,
        ),
        status=200,
        mimetype="application/json",
    )


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=True)
