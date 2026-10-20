from flask import Flask, request, jsonify
import requests
import os
import json

app = Flask(__name__)

GITHUB_SEARCH_URL = "https://api.github.com/search/users"
GITHUB_USER_URL   = "https://api.github.com/users"


# ---------- Helper ----------
def gh_headers():
    headers = {
        "Accept": "application/vnd.github+json",
        "User-Agent": "flask-github-api/1.0",
    }
    token = os.getenv("GITHUB_TOKEN")  # optional (rate-limit বাড়ায়)
    if token:
        headers["Authorization"] = f"Bearer {token}"
    return headers


def pretty(obj, status=200):
    return app.response_class(
        response=json.dumps(obj, ensure_ascii=False, indent=2),
        status=status,
        mimetype="application/json",
    )


# ---------- 1) Search users ----------
@app.route("/github/search", methods=["GET"])
def github_search():
    query = request.args.get("q", "").strip()

    if not query:
        return pretty({
            "success": False,
            "message": "Query required",
            "use": "/github/search?q=<username>&per_page=30&page=1"
        }, 400)

    # pagination
    try:
        per_page = int(request.args.get("per_page", 30))
    except ValueError:
        per_page = 30
    per_page = max(1, min(per_page, 100))

    try:
        page = int(request.args.get("page", 1))
    except ValueError:
        page = 1
    page = max(1, page)

    # sort/order (optional)
    sort = request.args.get("sort")   # followers | repositories | joined
    order = request.args.get("order", "desc").lower()

    params = {"q": query, "per_page": per_page, "page": page}
    if sort in ("followers", "repositories", "joined"):
        params["sort"] = sort
        params["order"] = "asc" if order == "asc" else "desc"

    # request
    try:
        resp = requests.get(
            GITHUB_SEARCH_URL,
            headers=gh_headers(),
            params=params,
            timeout=15,
        )
    except requests.RequestException as e:
        return pretty({"success": False, "message": str(e)}, 500)

    # error handle
    if resp.status_code == 403:
        return pretty({
            "success": False,
            "message": "rate_limit_or_forbidden",
            "detail": resp.text[:300],
        }, 403)

    if resp.status_code == 422:
        return pretty({
            "success": False,
            "message": "validation_failed",
            "detail": resp.text[:300],
        }, 422)

    if resp.status_code != 200:
        return pretty({
            "success": False,
            "message": "github_error",
            "code": resp.status_code,
            "raw": resp.text[:500],
        }, 400)

    # JSON decode
    try:
        data = resp.json()
    except ValueError:
        return pretty({"success": False, "message": "invalid_json"}, 500)

    # build items
    items = []
    for it in data.get("items", []):
        items.append({
            "login":      it.get("login"),
            "id":         it.get("id"),
            "type":       it.get("type"),
            "avatar":     it.get("avatar_url"),
            "html_url":   it.get("html_url"),
            "score":      it.get("score"),
            "site_admin": it.get("site_admin", False),
        })

    result = {
        "success": True,
        "query": query,
        "total_count": data.get("total_count", 0),
        "incomplete_results": data.get("incomplete_results", False),
        "page": page,
        "per_page": per_page,
        "count": len(items),
        "items": items,
        "rate": {
            "limit":     resp.headers.get("X-RateLimit-Limit"),
            "remaining": resp.headers.get("X-RateLimit-Remaining"),
            "reset":     resp.headers.get("X-RateLimit-Reset"),
        },
    }
    return pretty(result)


# ---------- 2) (Bonus) Full user detail ----------
@app.route("/github/user/<username>", methods=["GET"])
def github_user(username):
    try:
        resp = requests.get(
            f"{GITHUB_USER_URL}/{username}",
            headers=gh_headers(),
            timeout=15,
        )
    except requests.RequestException as e:
        return pretty({"success": False, "message": str(e)}, 500)

    if resp.status_code == 404:
        return pretty({"success": False, "message": "not_found"}, 404)

    if resp.status_code != 200:
        return pretty({
            "success": False,
            "code": resp.status_code,
            "raw": resp.text[:300],
        }, 400)

    try:
        u = resp.json()
    except ValueError:
        return pretty({"success": False, "message": "invalid_json"}, 500)

    result = {
        "success": True,
        "info": {
            "login":            u.get("login"),
            "id":               u.get("id"),
            "name":             u.get("name"),
            "type":             u.get("type"),
            "company":          u.get("company"),
            "blog":             u.get("blog"),
            "location":         u.get("location"),
            "email":            u.get("email"),
            "bio":              u.get("bio"),
            "twitter_username": u.get("twitter_username"),
            "public_repos":     u.get("public_repos", 0),
            "public_gists":     u.get("public_gists", 0),
            "followers":        u.get("followers", 0),
            "following":        u.get("following", 0),
            "created_at":       u.get("created_at"),
            "updated_at":       u.get("updated_at"),
            "avatar":           u.get("avatar_url"),
            "html_url":         u.get("html_url"),
            "hireable":         u.get("hireable"),
            "site_admin":       u.get("site_admin", False),
        },
    }
    return pretty(result)


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=True)
