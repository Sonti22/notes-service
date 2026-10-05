"""Generate the checked-in OpenAPI contract (Python standard library only)."""
import json
from pathlib import Path

ref = lambda name: {"$ref": f"#/components/schemas/{name}"}
response = lambda description, schema: {"description": description, "content": {"application/json": {"schema": schema}}}
note_response = response("Заметка", {"type": "object", "required": ["data"], "properties": {"data": ref("Note")}})
errors = {"404": response("Заметка не найдена или неверный ID", ref("Error")), "422": response("Ошибка валидации", ref("ValidationError"))}
body = lambda schema: {"required": True, "content": {"application/json": {"schema": schema}}}
fields = {"title": {"type": "string", "minLength": 1, "maxLength": 200, "example": "Идея проекта"}, "content": {"type": "string", "minLength": 1, "maxLength": 10000, "example": "Создать сервис заметок"}}
spec = {
    "openapi": "3.0.3",
    "info": {"title": "Notes API", "version": "1.0.0", "description": "Сервис заметок. Авторизация не предусмотрена заданием. Все заметки общие. JSON-ошибки возвращаются независимо от Accept. Строки обрезаются по краям; пустые значения запрещены. Неизвестные поля тела игнорируются. Поиск SQLite LIKE регистронезависим для ASCII; для кириллицы учитывает регистр."},
    "servers": [{"url": "/api", "description": "Текущий сервер"}],
    "tags": [{"name": "Notes", "description": "CRUD заметок"}],
    "paths": {
        "/notes": {
            "get": {"tags": ["Notes"], "operationId": "listNotes", "summary": "Список заметок (новые первыми)", "parameters": [
                {"name": "page", "in": "query", "schema": {"type": "integer", "minimum": 1, "maximum": 1000000, "default": 1}},
                {"name": "per_page", "in": "query", "schema": {"type": "integer", "minimum": 1, "maximum": 100, "default": 12}},
                {"name": "search", "in": "query", "description": "Буквальная подстрока в заголовке или тексте", "schema": {"type": "string", "maxLength": 200}}
            ], "responses": {"200": response("Страница заметок", ref("NotePage")), "422": errors["422"]}},
            "post": {"tags": ["Notes"], "operationId": "createNote", "summary": "Создать заметку", "requestBody": body(ref("NoteInput")), "responses": {"201": {**note_response, "headers": {"Location": {"description": "URL созданной заметки", "schema": {"type": "string"}}}}, "422": errors["422"]}}
        },
        "/notes/{id}": {"parameters": [{"name": "id", "in": "path", "required": True, "description": "Положительный целочисленный ID, до 18 цифр", "schema": {"type": "integer", "minimum": 1, "maximum": 999999999999999999}}],
            "get": {"tags": ["Notes"], "operationId": "showNote", "summary": "Просмотреть заметку", "responses": {"200": note_response, "404": errors["404"]}},
            "put": {"tags": ["Notes"], "operationId": "replaceNote", "summary": "Обновить оба поля", "requestBody": body(ref("NoteInput")), "responses": {"200": note_response, **errors}},
            "patch": {"tags": ["Notes"], "operationId": "updateNote", "summary": "Изменить одно или оба поля", "requestBody": body({"type": "object", "properties": fields, "anyOf": [{"required": ["title"]}, {"required": ["content"]}]}), "responses": {"200": note_response, **errors}},
            "delete": {"tags": ["Notes"], "operationId": "deleteNote", "summary": "Удалить заметку", "responses": {"204": {"description": "Удалено, без тела ответа"}, "404": errors["404"]}}
        }
    },
    "components": {"schemas": {
        "NoteInput": {"type": "object", "required": ["title", "content"], "properties": fields},
        "Note": {"type": "object", "required": ["id", "title", "content", "created_at", "updated_at"], "properties": {"id": {"type": "integer", "minimum": 1}, **fields, "created_at": {"type": "string", "format": "date-time"}, "updated_at": {"type": "string", "format": "date-time"}}},
        "Error": {"type": "object", "required": ["message"], "properties": {"message": {"type": "string"}}},
        "ValidationError": {"type": "object", "required": ["message", "errors"], "properties": {"message": {"type": "string"}, "errors": {"type": "object", "additionalProperties": {"type": "array", "items": {"type": "string"}}}}},
        "NotePage": {"type": "object", "required": ["data", "links", "meta"], "properties": {
            "data": {"type": "array", "items": ref("Note")},
            "links": {"type": "object", "properties": {name: {"type": "string", "nullable": True} for name in ["first", "last", "prev", "next"]}},
            "meta": {"type": "object", "properties": {**{name: {"type": "integer"} for name in ["current_page", "last_page", "per_page", "total"]}, "from": {"type": "integer", "nullable": True}, "to": {"type": "integer", "nullable": True}, "path": {"type": "string"}, "links": {"type": "array", "items": {"type": "object"}}}}
        }}
    }}
}
target = Path(__file__).resolve().parents[1] / "backend/public/docs/openapi.json"
target.parent.mkdir(parents=True, exist_ok=True)
target.write_text(json.dumps(spec, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
print(target)
