"""Fetch Canvas quiz statistics and render them as a PDF."""

import logging
import os
import re

from xhtml2pdf import pisa

logger = logging.getLogger(__name__)


def fetch_quiz_statistics(client, course_id: str, quiz_id: int) -> dict | None:
    """Fetches quiz statistics from the Canvas API.

    Returns the first quiz_statistics object, or None on failure.
    """
    data = client.api_request(f"courses/{course_id}/quizzes/{quiz_id}/statistics")
    if not data:
        return None
    stats_list = data.get("quiz_statistics", [])
    return stats_list[0] if stats_list else None


def render_quiz_statistics_pdf(
    client,
    course_id: str,
    quiz_id: int,
    quiz_title: str,
    output_path: str,
) -> str | None:
    """Fetches quiz statistics and renders them as a PDF.

    Args:
        client: CanvasGradesFetcher instance.
        course_id: Canvas course ID.
        quiz_id: Canvas quiz ID.
        quiz_title: Human-readable quiz name.
        output_path: Directory to save the PDF in.

    Returns:
        Absolute path to the generated PDF, or None on failure.
    """
    stats = fetch_quiz_statistics(client, course_id, quiz_id)
    if not stats:
        logger.warning("No statistics available for quiz %s", quiz_id)
        return None

    sub_stats = stats.get("submission_statistics", {})
    questions = stats.get("question_statistics", [])
    points = float(stats.get("points_possible") or 0)

    avg = float(sub_stats.get("score_average") or 0)
    high = float(sub_stats.get("score_high") or 0)
    low = float(sub_stats.get("score_low") or 0)
    stdev = float(sub_stats.get("score_stdev") or 0)
    duration = float(sub_stats.get("duration_average") or 0)
    unique = int(sub_stats.get("unique_count") or 0)

    html = _build_summary_html(
        quiz_title, points, avg, high, low, stdev, duration, unique
    )
    html += _build_score_distribution_html(sub_stats.get("scores", {}))
    html += _build_questions_html(questions)
    html += "</body></html>"

    os.makedirs(output_path, exist_ok=True)
    pdf_path = os.path.join(output_path, f"{_sanitize(quiz_title)}_statistics.pdf")

    try:
        with open(pdf_path, "wb") as pdf_file:
            status = pisa.CreatePDF(html, dest=pdf_file)
            if status.err:
                logger.error("xhtml2pdf errors rendering quiz stats: %s", status.err)
                return None
        logger.info("Quiz statistics PDF saved: %s", pdf_path)
        return pdf_path
    except Exception as e:
        logger.error("Failed to render quiz statistics PDF: %s", e)
        return None


# HTML builders


_STYLE = """
body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #333; margin: 30px; }
h1 { font-size: 20px; color: #2d3b45; border-bottom: 2px solid #0076b6; padding-bottom: 8px; }
h2 { font-size: 14px; color: #2d3b45; margin-top: 20px; border-bottom: 1px solid #ccc; padding-bottom: 5px; }
table { width: 100%; border-collapse: collapse; margin: 10px 0 20px 0; }
.summary-table td { padding: 6px 12px; border: 1px solid #ddd; }
.summary-table td:first-child { font-weight: bold; background: #f5f5f5; width: 200px; }
.question-box { width: 100%; margin: 15px 0; padding: 10px; border: 1px solid #e0e0e0; background: #fafafa; }
.question-text { font-weight: bold; font-size: 12px; margin-bottom: 8px; }
.answer-table th { text-align: left; padding: 4px 8px; background: #e8e8e8; border: 1px solid #ddd; font-size: 10px; }
.answer-table td { padding: 4px 8px; border: 1px solid #ddd; font-size: 10px; }
.correct { color: #0a7c3a; font-weight: bold; }
"""


def _build_summary_html(title, points, avg, high, low, stdev, duration, unique):
    return f"""<html>
<head><style>{_STYLE}</style></head>
<body>
<h1>{title}: Statistics</h1>
<h2>Quiz Summary</h2>
<table class="summary-table">
<tr><td>Points Possible</td><td>{points}</td></tr>
<tr><td>Average Score</td><td>{avg:.2f} / {points}</td></tr>
<tr><td>High Score</td><td>{high:.2f}</td></tr>
<tr><td>Low Score</td><td>{low:.2f}</td></tr>
<tr><td>Standard Deviation</td><td>{stdev:.2f}</td></tr>
<tr><td>Average Time</td><td>{duration:.0f} seconds</td></tr>
<tr><td>Submissions</td><td>{unique}</td></tr>
</table>"""


def _build_score_distribution_html(scores: dict) -> str:
    if not scores:
        return ""
    html = """<h2>Score Distribution</h2>
<table class="answer-table">
<tr><th>Score (%)</th><th>Count</th></tr>"""
    for pct in sorted(scores.keys(), key=lambda x: float(x), reverse=True):
        count = scores[pct]
        html += f"<tr><td>{pct}%</td><td>{count}</td></tr>"
    html += "</table>"
    return html


def _build_questions_html(questions: list[dict]) -> str:
    html = ""
    for q in sorted(questions, key=lambda q: q.get("position", 0)):
        q_text = re.sub(r"<[^>]+>", "", q.get("question_text", "")).strip()
        total_resp = q.get("responses", 0)
        correct_count = q.get("correct", "N/A")
        q_type = q.get("question_type", "").replace("_", " ").title()

        html += f"""
<div class="question-box">
<div class="question-text">Question {q['position']}: {q_text}</div>
<p>Type: {q_type} | Responses: {total_resp} | Correct: {correct_count}</p>
<table class="answer-table">
<tr><th>Answer</th><th>Correct?</th><th>Responses</th><th>%</th></tr>"""

        for a in q.get("answers", []):
            a_text = a.get("text", "N/A")
            is_correct = "Yes" if a.get("correct") else "No"
            resp = a.get("responses", 0)
            pct = (resp / total_resp * 100) if total_resp > 0 else 0
            css = ' class="correct"' if a.get("correct") else ""
            html += (
                f"<tr><td{css}>{a_text}</td><td{css}>{is_correct}</td>"
                f"<td>{resp}</td><td>{pct:.0f}%</td></tr>"
            )
        html += "</table></div>"
    return html


def _sanitize(name: str) -> str:
    return re.sub(r'[<>:"/\\|?*]', "", name).strip()
