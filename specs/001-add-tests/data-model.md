# Data Model: Add Automated Tests

## Entities

### TestFixture

- **Purpose**: Represents a deterministic input file used by tests.
- **Fields**:
  - name
  - content
  - size
  - encoding
- **Validation**:
  - Content must be deterministic and self-contained.
  - File size must be within bounds for test runtime.

### SampleConfig

- **Purpose**: Represents configuration variants for parsing tests.
- **Fields**:
  - path
  - contents
  - required_keys
  - optional_keys
- **Validation**:
  - Required keys must be present.
  - Invalid fields must produce actionable error messages.

### ExpectedOutput

- **Purpose**: Represents expected output artifacts for a fixture run.
- **Fields**:
  - source_fixture
  - output_format
  - output_content
- **Validation**:
  - Output must match the format and content derived from the fixture.
