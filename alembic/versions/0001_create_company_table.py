"""create company table

Revision ID: 0001_create_company_table
Revises:
Create Date: 2026-04-07 00:00:00.000000
"""
from alembic import op
import sqlalchemy as sa

# revision identifiers, used by Alembic.
revision = '0001_create_company_table'
down_revision = None
branch_labels = None
depends_on = None


def upgrade():
    op.create_table(
        'company',
        sa.Column('id', sa.Integer, primary_key=True),
        sa.Column('legal_number', sa.String(11), nullable=False, unique=True),
        sa.Column('legal_name', sa.String(250), nullable=False),
        sa.Column('trade_name', sa.String(250), nullable=True),
        sa.Column('sunat_mode', sa.String(10), nullable=False),
        sa.Column('soap_send_type', sa.String(10), nullable=False),
        sa.Column('soap_username', sa.String(200), nullable=True),
        sa.Column('soap_password', sa.String(500), nullable=True),
        sa.Column('certificate_password', sa.String(200), nullable=True),
        sa.Column('certificate_format', sa.String(10), nullable=True),
        sa.Column('certificate_path', sa.String(1000), nullable=True),
        sa.Column('pse_provider', sa.String(50), nullable=True),
        sa.Column('pse_username', sa.String(200), nullable=True),
        sa.Column('pse_password', sa.String(500), nullable=True),
        sa.Column('pse_token_url', sa.String(1000), nullable=True),
        sa.Column('pse_generate_url', sa.String(1000), nullable=True),
        sa.Column('pse_send_url', sa.String(1000), nullable=True),
        sa.Column('pse_query_url', sa.String(1000), nullable=True),
        sa.Column('send_document_to_pse', sa.Boolean, nullable=False, server_default=sa.false()),
        sa.Column('response_mode', sa.String(20), nullable=False, server_default='hybrid'),
        sa.Column('wait_seconds', sa.Integer, nullable=False, server_default='10'),
        sa.Column('auto_poll_ticket', sa.Boolean, nullable=False, server_default=sa.false()),
        sa.Column('ticket_wait_seconds', sa.Integer, nullable=False, server_default='60'),
        sa.Column('ticket_poll_interval_ms', sa.Integer, nullable=False, server_default='1000'),
        sa.Column('api_token', sa.String(500), nullable=True),
        sa.Column('status', sa.String(20), nullable=False, server_default='active'),
        sa.Column('created_at', sa.DateTime(timezone=True), server_default=sa.func.now()),
        sa.Column('updated_at', sa.DateTime(timezone=True), nullable=True),
    )


def downgrade():
    op.drop_table('company')
