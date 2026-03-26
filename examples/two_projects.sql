select coalesce(max(max_value) + 1, 1) as next_value
from (
        (
            select max(cast(value as SIGNED)) as max_value
            from [data-table]
            where project_id = [project-id]
                and field_name = [field-name]
                and record = [record-name]
        )

        union

        (
            select max(cast(value as SIGNED)) as max_value
            from [data-table:pid-1]
            where project_id = [pid-1]
                and field_name = [field-name]
                and record = [record-name]
        )
    ) as dummy
