select coalesce(max(max_value) + 1, 1) as next_value
from (
        (
            select max(cast(value as SIGNED)) as max_value
            from [data-table]
            where project_id = [project_id]
                and field_name = [field_name]
                and record = [record_id]
        )

        union

        (
            select max(cast(value as SIGNED)) as max_value
            from [data-table:pid1]
            where project_id = [pid1]
                and field_name = [field_name]
                and record = [record_id]
        )
    ) as dummy
