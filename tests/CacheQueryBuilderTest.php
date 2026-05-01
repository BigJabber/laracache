<?php

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Expression as Raw;
use Laracache\Cache\Query\Builder;
use Laracache\Cache\Query\Grammars\Grammar;
use Laracache\Cache\Query\Processors\Processor;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class CacheQueryBuilderTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    protected function getBuilder()
    {
        $grammar = new Grammar($this->getConnection());
        $processor = m::mock(Processor::class);

        return new Builder(
            m::mock(ConnectionInterface::class),
            $grammar,
            $processor
        );
    }

    public function test_basic_select()
    {
        $builder = $this->getBuilder();

        $builder->select('*')->from('users');
        $this->assertEquals('select * from users', $builder->toSql());
    }

    public function test_basic_select_with_reserved_words()
    {
        $builder = $this->getBuilder();

        $builder->select('exists', 'drop', 'group')->from('users');
        $this->assertEquals('select exists, drop, group from users', $builder->toSql());
    }

    public function test_adding_selects()
    {
        $builder = $this->getBuilder();

        $builder->select('foo')
            ->addSelect('bar')
            ->addSelect(['baz', 'boom'])
            ->from('users');

        $this->assertEquals(
            'select foo, bar, baz, boom from users',
            $builder->toSql()
        );
    }

    public function test_basic_select_distinct()
    {
        $builder = $this->getBuilder();

        $builder->distinct()->select('foo', 'bar')->from('users');

        $this->assertEquals('select distinct foo, bar from users', $builder->toSql());
    }

    public function test_basic_alias()
    {
        $builder = $this->getBuilder();

        $builder->select('foo as bar')->from('users');

        $this->assertEquals('select foo as bar from users', $builder->toSql());
    }

    public function test_basic_schema_wrapping()
    {
        $builder = $this->getBuilder();

        $builder->select('*')->from('acme.users');

        $this->assertEquals('select * from acme.users', $builder->toSql());
    }

    public function test_basic_schema_wrapping_reserved_words()
    {
        $builder = $this->getBuilder();

        $builder->select('*')->from('schema.users');

        $this->assertEquals('select * from schema.users', $builder->toSql());
    }

    public function test_basic_column_wrapping_reserved_words()
    {
        $builder = $this->getBuilder();

        $builder->select('order')->from('users');

        $this->assertEquals('select order from users', $builder->toSql());
    }

    public function test_basic_wheres()
    {
        $builder = $this->getBuilder();

        $builder->select('*')->from('users')->where('id', '=', 1);

        $this->assertEquals('select * from users where id = ?', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function test_basic_wheres_with_reserved_words()
    {
        $builder = $this->getBuilder();

        $builder->select('*')->from('users')->where('blob', '=', 1);

        $this->assertEquals('select * from users where blob = ?', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function test_where_between()
    {
        $builder = $this->getBuilder();

        $builder->select('*')->from('users')->whereBetween('id', [1, 2]);

        $this->assertEquals(
            'select * from users where id between ? and ?',
            $builder->toSql()
        );
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNotBetween('id', [1, 2]);

        $this->assertEquals(
            'select * from users where id not between ? and ?',
            $builder->toSql()
        );
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
    }

    public function test_basic_or_wheres()
    {
        $builder = $this->getBuilder();

        $builder->select('*')
            ->from('users')
            ->where('id', '=', 1)
            ->orWhere('email', '=', 'foo');

        $this->assertEquals(
            'select * from users where id = ? or email = ?',
            $builder->toSql()
        );
        $this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());
    }

    public function test_raw_wheres()
    {
        $builder = $this->getBuilder();

        $builder->select('*')
            ->from('users')
            ->whereRaw('id = ? or email = ?', [1, 'foo']);

        $this->assertEquals(
            'select * from users where id = ? or email = ?',
            $builder->toSql()
        );
        $this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());
    }

    public function test_raw_or_wheres()
    {
        $builder = $this->getBuilder();

        $builder->select('*')
            ->from('users')
            ->where('id', '=', 1)
            ->orWhereRaw('email = ?', ['foo']);

        $this->assertEquals(
            'select * from users where id = ? or email = ?',
            $builder->toSql()
        );
        $this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());
    }

    public function test_basic_where_ins()
    {
        $builder = $this->getBuilder();

        $builder->select('*')
            ->from('users')
            ->whereIn('id', [1, 2, 3]);

        $this->assertEquals(
            'select * from users where id in (?, ?, ?)',
            $builder->toSql()
        );
        $this->assertEquals([0 => 1, 1 => 2, 2 => 3], $builder->getBindings());

        $builder = $this->getBuilder();

        $builder->select('*')
            ->from('users')
            ->where('id', '=', 1)
            ->orWhereIn('id', [1, 2, 3]);

        $this->assertEquals(
            'select * from users where id = ? or id in (?, ?, ?)',
            $builder->toSql()
        );
        $this->assertEquals([0 => 1, 1 => 1, 2 => 2, 3 => 3], $builder->getBindings());
    }

    public function test_basic_where_not_ins()
    {
        $builder = $this->getBuilder();

        $builder->select('*')
            ->from('users')
            ->whereNotIn('id', [1, 2, 3]);

        $this->assertEquals(
            'select * from users where id not in (?, ?, ?)',
            $builder->toSql()
        );
        $this->assertEquals([0 => 1, 1 => 2, 2 => 3], $builder->getBindings());

        $builder = $this->getBuilder();

        $builder->select('*')
            ->from('users')
            ->where('id', '=', 1)
            ->orWhereNotIn('id', [1, 2, 3]);

        $this->assertEquals(
            'select * from users where id = ? or id not in (?, ?, ?)',
            $builder->toSql()
        );
        $this->assertEquals([0 => 1, 1 => 1, 2 => 2, 3 => 3], $builder->getBindings());
    }

    public function test_unions()
    {
        $builder = $this->getBuilder();

        $builder->select('*')
            ->from('users')
            ->where('id', '=', 1);

        $builder->union(
            $this->getBuilder()->select('*')->from('users')->where('id', '=', 2)
        );

        $this->assertEquals(
            'select * from (select * from users where id = ?) as temp_table union select * from (select * from users where id = ?) as temp_table',
            $builder->toSql()
        );
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
    }

    public function test_unions_with_custom_select()
    {
        $builder = $this->getBuilder();

        $builder->select(['name', 'email'])
            ->from('users')
            ->where('id', '=', 1);

        $builder->union(
            $this->getBuilder()->select(['name', 'email'])->from('users')->where('id', '=', 2)
        );

        $this->assertEquals(
            'select * from (select name, email from users where id = ?) as temp_table union select * from (select name, email from users where id = ?) as temp_table',
            $builder->toSql()
        );
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
    }

    public function test_union_alls()
    {
        $builder = $this->getBuilder();

        $builder->select('*')
            ->from('users')
            ->where('id', '=', 1);

        $builder->unionAll(
            $this->getBuilder()->select('*')->from('users')->where('id', '=', 2)
        );

        $this->assertEquals(
            'select * from (select * from users where id = ?) as temp_table union all select * from (select * from users where id = ?) as temp_table',
            $builder->toSql()
        );
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
    }

    public function test_union_alls_with_custom_select()
    {
        $builder = $this->getBuilder();

        $builder->select(['name', 'email'])
            ->from('users')
            ->where('id', '=', 1);

        $builder->unionAll(
            $this->getBuilder()->select(['name', 'email'])->from('users')->where('id', '=', 2)
        );

        $this->assertEquals(
            'select * from (select name, email from users where id = ?) as temp_table union all select * from (select name, email from users where id = ?) as temp_table',
            $builder->toSql()
        );
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
    }

    public function test_multiple_unions()
    {
        $builder = $this->getBuilder();

        $builder->select('*')->from('users')->where('id', '=', 1);

        $builder->union(
            $this->getBuilder()->select('*')->from('users')->where('id', '=', 2)
        );

        $builder->union(
            $this->getBuilder()->select('*')->from('users')->where('id', '=', 3)
        );

        $this->assertEquals(
            'select * from (select * from users where id = ?) as temp_table union select * from (select * from users where id = ?) as temp_table union select * from (select * from users where id = ?) as temp_table',
            $builder->toSql()
        );
        $this->assertEquals([0 => 1, 1 => 2, 2 => 3], $builder->getBindings());
    }

    public function test_multiple_unions_with_custom_select()
    {
        $builder = $this->getBuilder();

        $builder->select(['name', 'email'])->from('users')->where('id', '=', 1);

        $builder->union(
            $this->getBuilder()->select(['name', 'email'])->from('users')->where('id', '=', 2)
        );

        $builder->union(
            $this->getBuilder()->select(['name', 'email'])->from('users')->where('id', '=', 3)
        );

        $this->assertEquals(
            'select * from (select name, email from users where id = ?) as temp_table union select * from (select name, email from users where id = ?) as temp_table union select * from (select name, email from users where id = ?) as temp_table',
            $builder->toSql()
        );
        $this->assertEquals([0 => 1, 1 => 2, 2 => 3], $builder->getBindings());
    }

    public function test_multiple_union_alls()
    {
        $builder = $this->getBuilder();

        $builder->select('*')->from('users')->where('id', '=', 1);

        $builder->unionAll(
            $this->getBuilder()->select('*')->from('users')->where('id', '=', 2)
        );
        $builder->unionAll(
            $this->getBuilder()->select('*')->from('users')->where('id', '=', 3)
        );

        $this->assertEquals(
            'select * from (select * from users where id = ?) as temp_table union all select * from (select * from users where id = ?) as temp_table union all select * from (select * from users where id = ?) as temp_table',
            $builder->toSql()
        );
        $this->assertEquals([0 => 1, 1 => 2, 2 => 3], $builder->getBindings());
    }

    public function test_multiple_union_alls_with_custom_select()
    {
        $builder = $this->getBuilder();

        $builder->select(['name', 'email'])->from('users')->where('id', '=', 1);

        $builder->unionAll(
            $this->getBuilder()->select(['name', 'email'])->from('users')->where('id', '=', 2)
        );
        $builder->unionAll(
            $this->getBuilder()->select(['name', 'email'])->from('users')->where('id', '=', 3)
        );

        $this->assertEquals(
            'select * from (select name, email from users where id = ?) as temp_table union all select * from (select name, email from users where id = ?) as temp_table union all select * from (select name, email from users where id = ?) as temp_table',
            $builder->toSql()
        );
        $this->assertEquals([0 => 1, 1 => 2, 2 => 3], $builder->getBindings());
    }

    public function test_sub_select_where_ins()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('getDatabaseName')->andReturn('');

        $builder->select('*')
            ->from('users')
            ->whereIn('id', function ($q) {
                $q->select('id')->from('users')->where('age', '>', 25)->take(3);
            });

        $this->assertEquals(
            'select * from users where id in (select top 3 id from users where age > ?)',
            $builder->toSql()
        );
        $this->assertEquals([25], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('getDatabaseName')->andReturn('');

        $builder->select('*')->from('users')->whereNotIn('id', function ($q) {
            $q->select('id')->from('users')->where('age', '>', 25)->take(3);
        });

        $this->assertEquals(
            'select * from users where id not in (select top 3 id from users where age > ?)',
            $builder->toSql()
        );
        $this->assertEquals([25], $builder->getBindings());
    }

    public function test_basic_where_nulls()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNull('id');

        $this->assertEquals(
            'select * from users where id is null',
            $builder->toSql()
        );
        $this->assertEquals([], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereNull('id');

        $this->assertEquals(
            'select * from users where id = ? or id is null',
            $builder->toSql()
        );
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function test_basic_where_not_nulls()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNotNull('id');

        $this->assertEquals(
            'select * from users where id is not null',
            $builder->toSql()
        );
        $this->assertEquals([], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')
            ->from('users')
            ->where('id', '>', 1)
            ->orWhereNotNull('id');

        $this->assertEquals(
            'select * from users where id > ? or id is not null',
            $builder->toSql()
        );
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function test_group_bys()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->groupBy('id', 'email');

        $this->assertEquals('select * from users group by id, email', $builder->toSql());
    }

    public function test_order_bys()
    {
        $builder = $this->getBuilder();
        $builder->select('*')
            ->from('users')
            ->orderBy('email')
            ->orderBy('age', 'desc');

        $this->assertEquals(
            'select * from users order by email asc, age desc',
            $builder->toSql()
        );

        $builder = $this->getBuilder();
        $builder->select('*')
            ->from('users')
            ->orderBy('email')
            ->orderByRaw('age ? desc', ['bar']);

        $this->assertEquals(
            'select * from users order by email asc, age ? desc',
            $builder->toSql()
        );
        $this->assertEquals(['bar'], $builder->getBindings());
    }

    public function test_havings()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->having('email', '>', 1);

        $this->assertEquals(
            'select * from users having email > ?',
            $builder->toSql()
        );

        $builder = $this->getBuilder();
        $builder->select('*')
            ->from('users')
            ->groupBy('email')
            ->having('email', '>', 1);

        $this->assertEquals(
            'select * from users group by email having email > ?',
            $builder->toSql()
        );

        $builder = $this->getBuilder();
        $builder->select('email as foo_email')
            ->from('users')
            ->having('foo_email', '>', 1);

        $this->assertEquals(
            'select email as foo_email from users having foo_email > ?',
            $builder->toSql()
        );
    }

    public function test_raw_havings()
    {
        $builder = $this->getBuilder();
        $builder->select('*')
            ->from('users')
            ->havingRaw('user_foo < user_bar');

        $this->assertEquals(
            'select * from users having user_foo < user_bar',
            $builder->toSql()
        );

        $builder = $this->getBuilder();
        $builder->select('*')
            ->from('users')
            ->having('baz', '=', 1)
            ->orHavingRaw('user_foo < user_bar');

        $this->assertEquals(
            'select * from users having baz = ? or user_foo < user_bar',
            $builder->toSql()
        );
    }

    public function test_offset()
    {
        $builder = $this->getBuilder();

        $builder->select('*')->from('users')->offset(10);

        $this->assertEquals(
            'select *, %vid from (select top all * from users order by 1) where %vid between 11 and 10',
            $builder->toSql()
        );
    }

    public function test_offset_and_order_by_take_offset()
    {
        $builder = $this->getBuilder();

        $builder->select('*')->from('users')->orderBy('age')->take(4)->offset(10);

        $this->assertEquals(
            'select *, %vid from (select top all * from users order by age asc) where %vid between 11 and 14',
            $builder->toSql()
        );
    }

    public function test_offset_with_custom_select()
    {
        $builder = $this->getBuilder();

        $builder->select(['name', 'email'])->from('users')->offset(10);

        $this->assertEquals(
            'select *, %vid from (select top all name, email from users order by 1) where %vid between 11 and 10',
            $builder->toSql()
        );
    }

    public function test_limits_and_offsets()
    {
        $builder = $this->getBuilder();

        $builder->select('*')->from('users')->offset(5)->limit(10);

        $this->assertEquals(
            'select *, %vid from (select top all * from users order by 1) where %vid between 6 and 15',
            $builder->toSql()
        );

        $builder = $this->getBuilder();

        $builder->select('*')->from('users')->skip(5)->take(10);

        $this->assertEquals(
            'select *, %vid from (select top all * from users order by 1) where %vid between 6 and 15',
            $builder->toSql()
        );

        $builder = $this->getBuilder();

        $builder->select('*')->from('users')->skip(-5)->take(10);

        $this->assertEquals('select top 10 * from users', $builder->toSql());

        $builder = $this->getBuilder();

        $builder->select('*')->from('users')->forPage(2, 15);

        $this->assertEquals(
            'select *, %vid from (select top all * from users order by 1) where %vid between 16 and 30',
            $builder->toSql()
        );

        $builder = $this->getBuilder();

        $builder->select('*')->from('users')->forPage(-2, 15);

        $this->assertEquals(
            'select top 15 * from users',
            $builder->toSql()
        );
    }

    public function test_limits_and_offsets_with_custom_select()
    {
        $builder = $this->getBuilder();

        $builder->select(['name', 'email'])->from('users')->offset(5)->limit(10);

        $this->assertEquals(
            'select *, %vid from (select top all name, email from users order by 1) where %vid between 6 and 15',
            $builder->toSql()
        );
    }

    public function test_limit_and_offset_to_paginate_one()
    {
        $builder = $this->getBuilder();

        $builder->select('*')->from('users')->offset(0)->limit(1);

        $this->assertEquals(
            'select top 1 * from users',
            $builder->toSql()
        );

        $builder = $this->getBuilder();

        $builder->select('*')->from('users')->offset(1)->limit(1);

        $this->assertEquals(
            'select *, %vid from (select top all * from users order by 1) where %vid between 2 and 2',
            $builder->toSql()
        );
    }

    public function test_limit_and_offset_to_paginate_one_with_custom_select()
    {
        $builder = $this->getBuilder();

        $builder->select(['name', 'email'])->from('users')->offset(0)->limit(1);

        $this->assertEquals(
            'select top 1 name, email from users',
            $builder->toSql()
        );

        $builder = $this->getBuilder();

        $builder->select(['name', 'email'])->from('users')->offset(1)->limit(1);

        $this->assertEquals(
            'select *, %vid from (select top all name, email from users order by 1) where %vid between 2 and 2',
            $builder->toSql()
        );
    }

    public function test_where_shortcut()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', 1)->orWhere('name', 'foo');

        $this->assertEquals(
            'select * from users where id = ? or name = ?',
            $builder->toSql()
        );
        $this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());
    }

    public function test_nested_wheres()
    {
        $builder = $this->getBuilder();
        $builder->select('*')
            ->from('users')
            ->where('email', '=', 'foo')
            ->orWhere(function ($q) {
                $q->where('name', '=', 'bar')
                    ->where('email', '=', 'foo@bar.baz');
            });

        $this->assertEquals(
            'select * from users where email = ? or (name = ? and email = ?)',
            $builder->toSql()
        );
        $this->assertEquals(
            [0 => 'foo', 1 => 'bar', 2 => 'foo@bar.baz'],
            $builder->getBindings()
        );
    }

    public function test_full_sub_selects()
    {
        $builder = $this->getBuilder();
        $builder
            ->select('*')
            ->from('users')
            ->where('email', '=', 'foo')
            ->orWhere('id', '=', function ($q) {
                $q->select(new Raw('max(id)'))->from('users')->where('email', '=', 'bar');
            });

        $this->assertEquals(
            'select * from users where email = ? or id = (select max(id) from users where email = ?)',
            $builder->toSql()
        );

        $this->assertEquals([0 => 'foo', 1 => 'bar'], $builder->getBindings());
    }

    public function test_where_exists()
    {
        $builder = $this->getBuilder();
        $builder->select('*')
            ->from('orders')
            ->whereExists(function ($q) {
                $q->select('*')
                    ->from('products')
                    ->where('products.id', '=', new Raw('orders.id'));
            });

        $this->assertEquals(
            'select * from orders where exists (select * from products where products.id = orders.id)',
            $builder->toSql()
        );

        $builder = $this->getBuilder();
        $builder->select('*')
            ->from('orders')
            ->whereNotExists(function ($q) {
                $q->select('*')->from('products')->where('products.id', '=', new Raw('orders.id'));
            });

        $this->assertEquals(
            'select * from orders where not exists (select * from products where products.id = orders.id)',
            $builder->toSql()
        );

        $builder = $this->getBuilder();
        $builder->select('*')
            ->from('orders')
            ->where('id', '=', 1)
            ->orWhereExists(function ($q) {
                $q->select('*')
                    ->from('products')
                    ->where('products.id', '=', new Raw('orders.id'));
            });

        $this->assertEquals(
            'select * from orders where id = ? or exists (select * from products where products.id = orders.id)',
            $builder->toSql()
        );

        $builder = $this->getBuilder();
        $builder->select('*')
            ->from('orders')
            ->where('id', '=', 1)
            ->orWhereNotExists(function ($q) {
                $q->select('*')->from('products')->where('products.id', '=', new Raw('orders.id'));
            });
        $this->assertEquals(
            'select * from orders where id = ? or not exists (select * from products where products.id = orders.id)',
            $builder->toSql()
        );
    }

    public function test_basic_joins()
    {
        $builder = $this->getBuilder();
        $builder->select('*')
            ->from('users')
            ->join('contacts', 'users.id', '=', 'contacts.id')
            ->leftJoin('photos', 'users.id', '=', 'photos.id');

        $this->assertEquals(
            'select * from users inner join contacts on users.id = contacts.id left join photos on users.id = photos.id',
            $builder->toSql()
        );

        $builder = $this->getBuilder();
        $builder->select('*')
            ->from('users')
            ->leftJoinWhere('photos', 'users.id', '=', 'bar')
            ->joinWhere('photos', 'users.id', '=', 'foo');

        $this->assertEquals(
            'select * from users left join photos on users.id = ? inner join photos on users.id = ?',
            $builder->toSql()
        );
        $this->assertEquals(['bar', 'foo'], $builder->getBindings());
    }

    public function test_complex_join()
    {
        $builder = $this->getBuilder();
        $builder->select('*')
            ->from('users')
            ->join('contacts', function ($j) {
                $j->on('users.id', '=', 'contacts.id')
                    ->orOn('users.name', '=', 'contacts.name');
            });

        $this->assertEquals(
            'select * from users inner join contacts on users.id = contacts.id or users.name = contacts.name',
            $builder->toSql()
        );

        $builder = $this->getBuilder();
        $builder->select('*')
            ->from('users')
            ->join('contacts', function ($j) {
                $j->where('users.id', '=', 'foo')
                    ->orWhere('users.name', '=', 'bar');
            });
        $this->assertEquals(
            'select * from users inner join contacts on users.id = ? or users.name = ?',
            $builder->toSql()
        );
        $this->assertEquals(['foo', 'bar'], $builder->getBindings());
    }

    public function test_raw_expressions_in_select()
    {
        $builder = $this->getBuilder();
        $builder->select(new Raw('substr(foo, 6)'))->from('users');

        $this->assertEquals('select substr(foo, 6) from users', $builder->toSql());
    }

    public function test_list_methods_gets_array_of_column_values()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->andReturn([['foo' => 'bar'], ['foo' => 'baz']]);

        $builder->getProcessor()
            ->shouldReceive('processSelect')
            ->once()
            ->with($builder, [['foo' => 'bar'], ['foo' => 'baz']])
            ->andReturnUsing(function ($query, $results) {
                return $results;
            });

        $results = $builder->from('users')->where('id', '=', 1)->pluck('foo');

        $this->assertEquals(['bar', 'baz'], $results->all());

        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->andReturn([
                ['id' => 1, 'foo' => 'bar'],
                ['id' => 10, 'foo' => 'baz'],
            ]);

        $builder->getProcessor()
            ->shouldReceive('processSelect')
            ->once()
            ->with($builder, [['id' => 1, 'foo' => 'bar'], ['id' => 10, 'foo' => 'baz']])
            ->andReturnUsing(function ($query, $results) {
                return $results;
            });

        $results = $builder->from('users')->where('id', '=', 1)->pluck('foo', 'id');

        $this->assertEquals([1 => 'bar', 10 => 'baz'], $results->all());
    }

    public function test_implode()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->andReturn([
                ['foo' => 'bar'],
                ['foo' => 'baz'],
            ]);

        $builder->getProcessor()
            ->shouldReceive('processSelect')
            ->once()
            ->with($builder, [['foo' => 'bar'], ['foo' => 'baz']])
            ->andReturnUsing(function ($query, $results) {
                return $results;
            });

        $results = $builder->from('users')->where('id', '=', 1)->implode('foo');

        $this->assertEquals('barbaz', $results);

        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->andReturn([
                ['foo' => 'bar'],
                ['foo' => 'baz'],
            ]);

        $builder->getProcessor()
            ->shouldReceive('processSelect')
            ->once()
            ->with($builder, [['foo' => 'bar'], ['foo' => 'baz']])
            ->andReturnUsing(function ($query, $results) {
                return $results;
            });

        $results = $builder->from('users')->where('id', '=', 1)->implode('foo', ',');

        $this->assertEquals('bar,baz', $results);
    }

    public function test_aggregate_count_function()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->with('select count(*) as aggregate from users', [], true, [])
            ->andReturn([['aggregate' => 1]]);

        $builder->getProcessor()
            ->shouldReceive('processSelect')
            ->once()
            ->andReturnUsing(function ($builder, $results) {
                return $results;
            });

        $results = $builder->from('users')->count();

        $this->assertEquals(1, $results);
    }

    public function test_aggregate_max_function()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->with('select max(id) as aggregate from users', [], true, [])
            ->andReturn([['aggregate' => 1]]);

        $builder->getProcessor()
            ->shouldReceive('processSelect')
            ->once()
            ->andReturnUsing(function ($builder, $results) {
                return $results;
            });

        $results = $builder->from('users')->max('id');

        $this->assertEquals(1, $results);
    }

    public function test_aggregate_min_function()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->with('select min(id) as aggregate from users', [], true, [])
            ->andReturn([['aggregate' => 1]]);

        $builder->getProcessor()
            ->shouldReceive('processSelect')
            ->once()
            ->andReturnUsing(function ($builder, $results) {
                return $results;
            });

        $results = $builder->from('users')->min('id');

        $this->assertEquals(1, $results);
    }

    public function test_aggregate_sum_function()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->with('select sum(id) as aggregate from users', [], true, [])
            ->andReturn([['aggregate' => 1]]);

        $builder->getProcessor()
            ->shouldReceive('processSelect')
            ->once()
            ->andReturnUsing(function ($builder, $results) {
                return $results;
            });

        $results = $builder->from('users')->sum('id');

        $this->assertEquals(1, $results);
    }

    public function test_insert_method()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('insert')
            ->once()
            ->with('insert into users (email) values (?)', ['foo'])
            ->andReturn(true);

        $result = $builder->from('users')->insert(['email' => 'foo']);

        $this->assertTrue($result);
    }

    public function test_insert_method_respects_raw_bindings()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('insert')
            ->once()
            ->with('insert into users (email) values (CURRENT TIMESTAMP)', [])
            ->andReturn(true);

        $result = $builder->from('users')
            ->insert(['email' => new Raw('CURRENT TIMESTAMP')]);

        $this->assertTrue($result);
    }

    public function test_update_method()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('update')
            ->once()
            ->with('update users set email = ?, name = ? where id = ?', ['foo', 'bar', 1])
            ->andReturn(1);

        $result = $builder->from('users')
            ->where('id', '=', 1)
            ->update(['email' => 'foo', 'name' => 'bar']);

        $this->assertEquals(1, $result);
    }

    public function test_update_method_respects_raw()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('update')
            ->once()
            ->with('update users set email = foo, name = ? where id = ?', ['bar', 1])
            ->andReturn(1);

        $result = $builder
            ->from('users')
            ->where('id', '=', 1)
            ->update(['email' => new Raw('foo'), 'name' => 'bar']);

        $this->assertEquals(1, $result);
    }

    public function test_delete_method()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('delete')
            ->once()
            ->with('delete from users where email = ?', ['foo'])
            ->andReturn(1);

        $result = $builder->from('users')->where('email', '=', 'foo')->delete();

        $this->assertEquals(1, $result);

        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('delete')
            ->once()
            ->with('delete from users where users.id = ?', [1])
            ->andReturn(1);

        $result = $builder->from('users')->delete(1);

        $this->assertEquals(1, $result);
    }

    public function test_merge_wheres_can_merge_wheres_and_bindings()
    {
        $builder = $this->getBuilder();
        $builder->wheres = ['foo'];

        $builder->mergeWheres(['wheres'], [12 => 'foo', 13 => 'bar']);

        $this->assertEquals(['foo', 'wheres'], $builder->wheres);
        $this->assertEquals(['foo', 'bar'], $builder->getBindings());
    }

    public function test_providing_null_or_false_as_second_parameter_builds_correctly()
    {
        $builder = $this->getBuilder();

        $builder->select('*')->from('users')->where('foo', null);

        $this->assertEquals('select * from users where foo is null', $builder->toSql());
    }

    public function test_dynamic_where()
    {
        $method = 'whereFooBarAndBazOrQux';
        $parameters = ['corge', 'waldo', 'fred'];
        $grammar = new Grammar($this->getConnection());
        $processor = m::mock(Processor::class);
        $builder = m::mock(
            'Illuminate\Database\Query\Builder[where]',
            [m::mock('Illuminate\Database\ConnectionInterface'), $grammar, $processor]
        );

        $builder->shouldReceive('where')->with('foo_bar', '=', $parameters[0], 'and')->once()->andReturn($builder);
        $builder->shouldReceive('where')->with('baz', '=', $parameters[1], 'and')->once()->andReturn($builder);
        $builder->shouldReceive('where')->with('qux', '=', $parameters[2], 'or')->once()->andReturn($builder);

        $this->assertEquals($builder, $builder->dynamicWhere($method, $parameters));
    }

    protected function getConnection()
    {
        $connection = m::mock('Illuminate\Database\Connection');

        $connection->shouldReceive('getSchemaGrammar')->andReturn(new Grammar($connection));
        $connection->shouldReceive('getSchemaBuilder')->andReturn(Builder::class);
        $connection->shouldReceive('getConfig')->with('prefix_indexes')->andReturn(true);
        $connection->shouldReceive('getTablePrefix')->andReturn('');

        return $connection;
    }
}
