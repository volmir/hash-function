# Журнал выполнения заданий (память оркестратора)

- [P1] ✓ 2026-11-09 — get() → get32bit() — ИТОГ: Переименован `SimpleCryptoHash::get()` в `get32bit()`, обновлены вызовы и документация. Изменены: `src/SimpleCryptoHash.php`, `tests/Runner.php`, `README.md`, `docs/security-review.md`, `AGENTS.md`; `Data\*::get()` не трогались. (tier=light, model=gpt-5.6-luna, effort=low)
- [P2] ✓ 2026-11-09 — Пространство имён SimpleHash\ → Hash\ — ИТОГ: Пространство имён `SimpleHash\` переименовано в `Hash\` во всех указанных исходниках, тестах и документации. Имя `SimpleCryptoHash` и алгоритм не изменялись. (tier=light, model=gpt-5.6-luna, effort=low)
- [P3] ✓ 2026-11-09 — Класс SimpleCryptoHash → SimpleHash — ИТОГ: Переименован класс и файл в `Hash\SimpleHash`, обновлены тесты, таблицы и документация; алгоритм и хеши не изменялись. (tier=light, model=gpt-5.6-luna, effort=low)
- [P4] ✓ 2026-11-09 — Убрать антипример — ИТОГ: Полностью удалён антипример из кода, уровня 6, измерителей, README и AGENTS.md. Уровень 6 теперь сравнивает только `SimpleHash32` с библиотечными эталонами. (tier=light, model=gpt-5.6-luna, effort=low)
- [P5] ✓ 2026-11-09 — Вызов из командной строки — ИТОГ: Добавлена команда `hash.php` с выбором разрядности и значением 128 бит по умолчанию. Добавлены проверки CLI и обновлены README и AGENTS.md. (tier=standard, model=gpt-6.1-terra, effort=medium)
