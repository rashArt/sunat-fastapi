"""create document table

Revision ID: 0002_create_document_table
Revises: 0001_create_company_table
Create Date: 2026-04-14 00:00:00.000000
"""
from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects.postgresql import JSON

# revision identifiers, used by Alembic.
revision = '0002_create_document_table'
down_revision = '0001_create_company_table'
branch_labels = None
depends_on = None


def upgrade():
    op.create_table(
        'document',
        # Identificación
        sa.Column('id', sa.Integer, primary_key=True, autoincrement=True),
        sa.Column('company_id', sa.Integer,
                  sa.ForeignKey('company.id', ondelete='RESTRICT'),
                  nullable=False),
        sa.Column('external_id', sa.String(100), nullable=True),
        sa.Column('filename', sa.String(100), nullable=False),

        # Tipo de documento
        sa.Column('type', sa.String(40), nullable=False),
        sa.Column('document_type_id', sa.String(2), nullable=False),
        sa.Column('series', sa.String(10), nullable=False),
        sa.Column('number', sa.String(10), nullable=False),
        sa.Column('date_of_issue', sa.Date, nullable=True),

        # Estado del pipeline
        sa.Column('state_type_id', sa.String(2), nullable=False, server_default='01'),
        sa.Column('ticket', sa.String(50), nullable=True),

        # Respuesta SUNAT
        sa.Column('sunat_code', sa.String(10), nullable=True),
        sa.Column('sunat_description', sa.Text, nullable=True),
        sa.Column('sunat_notes', JSON, nullable=True),

        # Artefactos (rutas en filesystem)
        sa.Column('hash', sa.String(100), nullable=True),
        sa.Column('xml_unsigned_path', sa.String(300), nullable=True),
        sa.Column('xml_signed_path', sa.String(300), nullable=True),
        sa.Column('cdr_path', sa.String(300), nullable=True),

        # Payload JSON original para validación futura contra el XML
        sa.Column('request_payload', JSON, nullable=True),

        # Control
        sa.Column('attempt_count', sa.Integer, nullable=False, server_default='1'),
        sa.Column('processed_at', sa.DateTime(timezone=True), nullable=True),
        sa.Column('created_at', sa.DateTime(timezone=True),
                  server_default=sa.func.now(), nullable=False),
        sa.Column('updated_at', sa.DateTime(timezone=True), nullable=True),
    )

    # Idempotencia: un filename es único por compañía
    op.create_unique_constraint(
        'uq_document_company_filename',
        'document',
        ['company_id', 'filename'],
    )

    # Índices de consulta frecuente
    op.create_index('ix_document_company_id', 'document', ['company_id'])
    op.create_index('ix_document_company_external', 'document', ['company_id', 'external_id'])
    op.create_index('ix_document_state', 'document', ['state_type_id'])
    op.create_index('ix_document_company_ticket', 'document', ['company_id', 'ticket'])


def downgrade():
    op.drop_index('ix_document_company_ticket', table_name='document')
    op.drop_index('ix_document_state', table_name='document')
    op.drop_index('ix_document_company_external', table_name='document')
    op.drop_index('ix_document_company_id', table_name='document')
    op.drop_constraint('uq_document_company_filename', 'document', type_='unique')
    op.drop_table('document')
